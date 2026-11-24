import { beforeEach, describe, expect, it, vi } from "vitest"
import { api } from "@/lib/api"
import {
  archiveNotification,
  archiveReadNotifications,
  createTemplate,
  deleteTemplate,
  formatRelativeTime,
  getDeliveries,
  getDelivery,
  getNotification,
  getNotifications,
  getPreferences,
  getSettings,
  getTemplate,
  getTemplates,
  getUnreadCount,
  markAllNotificationsRead,
  markNotificationRead,
  requeueDelivery,
  savePreferences,
  severityLabel,
  statusLabel,
  updateSetting,
  updateTemplate,
  type AppNotification,
} from "./notifications-api"

vi.mock("@/lib/api", () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  },
}))

const response = (data: unknown) => ({ data }) as never

const notification: AppNotification = {
  id: "018f0000-0000-7000-8000-000000000001",
  type: "notification",
  attributes: {
    type: "payment.installment.overdue",
    title: "Installment overdue",
    body: "Installment F-012 is overdue",
    status: "unread",
    severity: "warning",
    visibleInApp: true,
    link: "/dashboard/installments",
    readAt: null,
    archivedAt: null,
    createdAt: "2026-11-16T10:00:00+00:00",
    updatedAt: "2026-11-16T10:00:00+00:00",
  },
}

const pagination = {
  currentPage: 1,
  totalPages: 3,
  pageSize: 20,
  totalItems: 51,
  links: { self: "/api/v1/notifications?page=1" },
}

const preference = {
  type: "payment.installment.overdue",
  channels: { in_app: true, email: false },
}

const template = {
  id: "018f0000-0000-7000-8000-000000000002",
  type: "invoice.issued",
  channel: "email" as const,
  locale: "en",
  subject: "Invoice {{ invoiceNumber }} issued",
  bodyTemplate: "<p>Invoice issued</p>",
  createdAt: "2026-11-16T10:00:00+00:00",
  updatedAt: "2026-11-16T10:00:00+00:00",
}

const setting = {
  key: "notifications.retention_days",
  value: "30",
  description: "Retention window",
}

const NOW = new Date("2026-11-16T12:00:00.000Z")

beforeEach(() => {
  vi.clearAllMocks()
})

describe("getNotifications", () => {
  it("maps the list envelope including meta.unreadCount", async () => {
    vi.mocked(api.get).mockResolvedValueOnce(
      response({
        status: "success",
        data: [notification],
        pagination,
        meta: { unreadCount: 3 },
      }),
    )

    await expect(
      getNotifications({
        page: 1,
        pageSize: 20,
        status: "unread",
        severity: "critical",
        search: "overdue",
      }),
    ).resolves.toEqual({ items: [notification], pagination, unreadCount: 3 })

    expect(api.get).toHaveBeenCalledWith("/api/notifications/notifications", {
      params: {
        page: 1,
        pageSize: 20,
        status: "unread",
        severity: "critical",
        search: "overdue",
      },
    })
  })

  it("defaults unreadCount to zero when meta is absent", async () => {
    vi.mocked(api.get).mockResolvedValueOnce(
      response({ status: "success", data: [], pagination }),
    )

    await expect(getNotifications({ page: 1, pageSize: 20 })).resolves.toEqual({
      items: [],
      pagination,
      unreadCount: 0,
    })
  })

  it("throws the API error message on an error envelope", async () => {
    vi.mocked(api.get).mockResolvedValueOnce(
      response({
        status: "error",
        error: { code: "FORBIDDEN", message: "Not allowed" },
      }),
    )

    await expect(getNotifications({ page: 1, pageSize: 20 })).rejects.toThrow(
      "Not allowed",
    )
  })
})

describe("filter encoding", () => {
  it("encodes filters into query params and omits empty ones", async () => {
    vi.mocked(api.get).mockResolvedValueOnce(
      response({ status: "success", data: [], pagination }),
    )

    await getDeliveries({
      page: 2,
      pageSize: 10,
      status: "failed",
      channel: "email",
      type: "invoice.issued",
      search: "user@example.com",
    })

    expect(api.get).toHaveBeenCalledWith("/api/notifications/admin/deliveries", {
      params: {
        page: 2,
        pageSize: 10,
        status: "failed",
        channel: "email",
        type: "invoice.issued",
        search: "user@example.com",
      },
    })

    vi.mocked(api.get).mockResolvedValueOnce(
      response({ status: "success", data: [], pagination }),
    )
    await getDeliveries({ page: 1, pageSize: 20 })

    expect(api.get).toHaveBeenLastCalledWith(
      "/api/notifications/admin/deliveries",
      { params: { page: 1, pageSize: 20 } },
    )
  })
})

describe("single item and unread count", () => {
  it("fetches a single notification", async () => {
    vi.mocked(api.get).mockResolvedValueOnce(
      response({ status: "success", data: notification }),
    )

    await expect(getNotification(notification.id)).resolves.toEqual(notification)
    expect(api.get).toHaveBeenCalledWith(
      `/api/notifications/notifications/${notification.id}`,
    )
  })

  it("unwraps {count} from the unread-count endpoint", async () => {
    vi.mocked(api.get).mockResolvedValueOnce(
      response({ status: "success", data: { count: 7 } }),
    )

    await expect(getUnreadCount()).resolves.toBe(7)
    expect(api.get).toHaveBeenCalledWith(
      "/api/notifications/notifications/unread-count",
    )
  })
})

describe("read/archive/requeue actions", () => {
  it("posts read/archive/requeue actions", async () => {
    vi.mocked(api.post).mockResolvedValue(
      response({ status: "success", data: notification }),
    )

    await expect(markNotificationRead(notification.id)).resolves.toEqual(
      notification,
    )
    expect(api.post).toHaveBeenCalledWith(
      `/api/notifications/notifications/${notification.id}/read`,
      {},
    )

    await expect(archiveNotification(notification.id)).resolves.toEqual(
      notification,
    )
    expect(api.post).toHaveBeenCalledWith(
      `/api/notifications/notifications/${notification.id}/archive`,
      {},
    )

    await expect(requeueDelivery("delivery-1")).resolves.toEqual(notification)
    expect(api.post).toHaveBeenCalledWith(
      "/api/notifications/admin/deliveries/delivery-1/requeue",
      {},
    )
  })

  it("returns the updated count from bulk actions", async () => {
    vi.mocked(api.post).mockResolvedValue(
      response({ status: "success", data: { updated: 4 } }),
    )

    await expect(markAllNotificationsRead()).resolves.toBe(4)
    expect(api.post).toHaveBeenCalledWith(
      "/api/notifications/notifications/read-all",
      {},
    )

    await expect(archiveReadNotifications()).resolves.toBe(4)
    expect(api.post).toHaveBeenCalledWith(
      "/api/notifications/notifications/archive-read",
      {},
    )
  })
})

describe("preferences", () => {
  it("sends preferences as {preferences:[...]}", async () => {
    vi.mocked(api.put).mockResolvedValueOnce(
      response({ status: "success", data: [preference] }),
    )

    const entries = [
      { type: "invoice.issued", channel: "email" as const, enabled: false },
    ]
    await expect(savePreferences(entries)).resolves.toEqual([preference])

    expect(api.put).toHaveBeenCalledWith("/api/notifications/preferences", {
      preferences: entries,
    })
  })

  it("loads the preference matrix", async () => {
    vi.mocked(api.get).mockResolvedValueOnce(
      response({ status: "success", data: [preference] }),
    )

    await expect(getPreferences()).resolves.toEqual([preference])
    expect(api.get).toHaveBeenCalledWith("/api/notifications/preferences")
  })
})

describe("admin templates and settings", () => {
  it("creates, updates and deletes templates", async () => {
    vi.mocked(api.post).mockResolvedValueOnce(
      response({ status: "success", data: template }),
    )
    const input = {
      type: "invoice.issued",
      channel: "email" as const,
      locale: "en",
      subject: template.subject,
      bodyTemplate: template.bodyTemplate,
    }
    await expect(createTemplate(input)).resolves.toEqual(template)
    expect(api.post).toHaveBeenCalledWith("/api/notifications/admin/templates", input)

    vi.mocked(api.put).mockResolvedValueOnce(
      response({ status: "success", data: template }),
    )
    await expect(updateTemplate(template.id, input)).resolves.toEqual(template)
    expect(api.put).toHaveBeenCalledWith(
      `/api/notifications/admin/templates/${template.id}`,
      input,
    )

    vi.mocked(api.delete).mockResolvedValueOnce(response(undefined))
    await expect(deleteTemplate(template.id)).resolves.toBeUndefined()
    expect(api.delete).toHaveBeenCalledWith(
      `/api/notifications/admin/templates/${template.id}`,
    )
  })

  it("loads a single delivery with its attempts", async () => {
    vi.mocked(api.get).mockResolvedValueOnce(
      response({ status: "success", data: { id: "delivery-1" } }),
    )

    await expect(getDelivery("delivery-1")).resolves.toEqual({
      id: "delivery-1",
    })
    expect(api.get).toHaveBeenCalledWith(
      "/api/notifications/admin/deliveries/delivery-1",
    )
  })

  it("loads all templates and a single template", async () => {
    vi.mocked(api.get).mockResolvedValueOnce(
      response({ status: "success", data: [template] }),
    )
    await expect(getTemplates()).resolves.toEqual([template])
    expect(api.get).toHaveBeenCalledWith("/api/notifications/admin/templates")

    vi.mocked(api.get).mockResolvedValueOnce(
      response({ status: "success", data: template }),
    )
    await expect(getTemplate(template.id)).resolves.toEqual(template)
    expect(api.get).toHaveBeenCalledWith(
      `/api/notifications/admin/templates/${template.id}`,
    )
  })

  it("loads and updates settings by key", async () => {
    vi.mocked(api.get).mockResolvedValueOnce(
      response({ status: "success", data: [setting] }),
    )
    await expect(getSettings()).resolves.toEqual([setting])
    expect(api.get).toHaveBeenCalledWith("/api/notifications/admin/settings")

    vi.mocked(api.put).mockResolvedValueOnce(
      response({ status: "success", data: setting }),
    )
    await expect(updateSetting("notifications.retention_days", "45")).resolves.toEqual(
      setting,
    )
    expect(api.put).toHaveBeenCalledWith(
      "/api/notifications/admin/settings/notifications.retention_days",
      { value: "45" },
    )
  })
})

describe("formatRelativeTime", () => {
  it("formats relative times for minutes, hours and days", () => {
    expect(formatRelativeTime("2026-11-16T11:55:00.000Z", NOW)).toBe(
      "5 minutes ago",
    )
    expect(formatRelativeTime("2026-11-16T09:00:00.000Z", NOW)).toBe(
      "3 hours ago",
    )
    expect(formatRelativeTime("2026-11-14T12:00:00.000Z", NOW)).toBe(
      "2 days ago",
    )
  })
})

describe("labels", () => {
  it("maps severities and statuses to human labels", () => {
    expect(severityLabel("critical")).toBe("Critical")
    expect(statusLabel("unread")).toBe("Unread")
  })
})
