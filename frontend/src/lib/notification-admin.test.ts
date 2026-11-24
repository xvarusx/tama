import { describe, expect, it } from "vitest"
import {
  buildDeliveryQuery,
  canRequeue,
  deliveryStatusLabel,
  validateSettingValue,
  validateTemplate,
} from "./notification-admin"
import type { TemplateInput } from "@/services/notifications-api"

describe("template form validation", () => {
  it("validates a template requires type, locale, subject and body", () => {
    const empty: TemplateInput = {
      type: "",
      channel: "email",
      locale: "",
      subject: "",
      bodyTemplate: "",
    }
    expect(validateTemplate(empty)).toEqual({
      type: "Type is required.",
      locale: "Locale is required.",
      subject: "Subject is required.",
      bodyTemplate: "Body is required.",
    })

    const valid: TemplateInput = {
      type: "invoice.issued",
      channel: "email",
      locale: "en",
      subject: "Invoice issued",
      bodyTemplate: "<p>Invoice issued</p>",
    }
    expect(validateTemplate(valid)).toEqual({})
  })
})

describe("delivery status helpers", () => {
  it("maps delivery status labels and requeue availability", () => {
    expect(deliveryStatusLabel("queued")).toBe("Queued")
    expect(deliveryStatusLabel("retry_waiting")).toBe("Retry waiting")
    expect(deliveryStatusLabel("failed")).toBe("Failed")

    expect(canRequeue("failed")).toBe(true)
    expect(canRequeue("sent")).toBe(false)
    expect(canRequeue("queued")).toBe(false)
  })
})

describe("setting validation", () => {
  it("validates numeric setting keys before saving", () => {
    expect(validateSettingValue("notifications.retention_days", "30")).toBeNull()
    expect(validateSettingValue("notifications.retention_days", "-1")).toBe(
      "Enter a positive whole number.",
    )
    expect(validateSettingValue("mailer.from_name", "Acme")).toBeNull()
  })
})

describe("delivery filters", () => {
  it("builds a delivery query from filters", () => {
    expect(
      buildDeliveryQuery({
        status: "failed",
        channel: "email",
        search: "user@example.com",
        page: 2,
        pageSize: 10,
      }),
    ).toEqual({
      page: 2,
      pageSize: 10,
      status: "failed",
      channel: "email",
      search: "user@example.com",
    })

    expect(
      buildDeliveryQuery({
        status: "all",
        channel: "all",
        search: "",
        page: 1,
        pageSize: 20,
      }),
    ).toEqual({ page: 1, pageSize: 20 })
  })
})
