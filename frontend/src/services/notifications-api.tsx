import { api } from "@/lib/api"

export type NotificationStatus = "unread" | "read" | "archived"
export type NotificationSeverity = "info" | "success" | "warning" | "critical"
export type NotificationChannel = "in_app" | "email"
export type DeliveryStatus =
  | "queued"
  | "sending"
  | "sent"
  | "retry_waiting"
  | "failed"

export interface NotificationAttributes {
  type: string
  title: string
  body: string
  status: NotificationStatus
  severity: NotificationSeverity
  visibleInApp: boolean
  link: string | null
  readAt: string | null
  archivedAt: string | null
  createdAt: string
  updatedAt: string
}

export interface AppNotification {
  id: string
  type: "notification"
  attributes: NotificationAttributes
}

export interface NotificationPagination {
  currentPage: number
  totalPages: number
  pageSize: number
  totalItems: number
  links: Record<string, string>
}

export interface NotificationsPage {
  items: AppNotification[]
  pagination: NotificationPagination
  unreadCount: number
}

export interface NotificationQuery {
  page: number
  pageSize: number
  status?: NotificationStatus
  severity?: NotificationSeverity
  type?: string
  search?: string
}

export interface NotificationPreference {
  type: string
  channels: Record<NotificationChannel, boolean>
}

export interface PreferenceEntry {
  type: string
  channel: NotificationChannel
  enabled: boolean
}

export interface DeliveryAttempt {
  attemptNumber: number
  providerMessageId: string | null
  error: string | null
  attemptedAt: string
}

export interface Delivery {
  id: string
  notificationId: string
  notificationType: string
  status: DeliveryStatus
  channel: NotificationChannel
  recipientEmail: string
  subject: string
  body?: string
  attemptCount: number
  lastError: string | null
  nextAttemptAt: string | null
  sendingAt: string | null
  sentAt: string | null
  createdAt: string
  updatedAt: string
  attempts?: DeliveryAttempt[]
}

export interface DeliveriesPage {
  items: Delivery[]
  pagination: NotificationPagination
}

export interface DeliveryQuery {
  page: number
  pageSize: number
  status?: DeliveryStatus
  channel?: NotificationChannel
  type?: string
  search?: string
}

export interface TemplateInput {
  type: string
  channel: NotificationChannel
  locale: string
  subject: string
  bodyTemplate: string
}

export interface Template {
  id: string
  type: string
  channel: NotificationChannel
  locale: string
  subject: string
  bodyTemplate: string
  createdAt?: string
  updatedAt?: string
}

export interface Setting {
  key: string
  value: string
  description?: string
}

interface ApiEnvelope<T> {
  status?: string
  data?: T
  pagination?: NotificationPagination
  meta?: { unreadCount?: number }
  error?: { code?: string; message?: string } | string
}

function envelopeError(error: ApiEnvelope<unknown>["error"]): string {
  if (typeof error === "string") return error
  return error?.message ?? "The API returned an error."
}

function unwrap<T>(envelope: ApiEnvelope<T>): T {
  if (envelope.status === "error") throw new Error(envelopeError(envelope.error))

  if (envelope.data === undefined) {
    throw new Error("The API returned an unexpected response.")
  }

  return envelope.data
}

const BASE = "/api/notifications"

export async function getNotifications(
  query: NotificationQuery,
): Promise<NotificationsPage> {
  const params: Record<string, string | number | boolean> = {
    page: query.page,
    pageSize: query.pageSize,
  }

  if (query.status) params.status = query.status
  if (query.severity) params.severity = query.severity
  if (query.type) params.type = query.type
  if (query.search) params.search = query.search

  const response = await api.get<ApiEnvelope<AppNotification[]>>(
    `${BASE}/notifications`,
    { params },
  )

  const items = unwrap(response.data)

  if (!response.data.pagination) {
    throw new Error("The API returned an unexpected response.")
  }

  return {
    items,
    pagination: response.data.pagination,
    unreadCount: response.data.meta?.unreadCount ?? 0,
  }
}

export async function getUnreadCount(): Promise<number> {
  const response = await api.get<ApiEnvelope<{ count: number } | number>>(
    `${BASE}/notifications/unread-count`,
  )

  const data = unwrap(response.data)

  if (typeof data === "number") return data

  return data.count
}

export async function getNotification(id: string): Promise<AppNotification> {
  const response = await api.get<ApiEnvelope<AppNotification>>(
    `${BASE}/notifications/${id}`,
  )

  return unwrap(response.data)
}

export async function markNotificationRead(
  id: string,
): Promise<AppNotification> {
  const response = await api.post<ApiEnvelope<AppNotification>>(
    `${BASE}/notifications/${id}/read`,
    {},
  )

  return unwrap(response.data)
}

export async function markAllNotificationsRead(): Promise<number> {
  const response = await api.post<ApiEnvelope<{ updated: number } | number>>(
    `${BASE}/notifications/read-all`,
    {},
  )

  const data = unwrap(response.data)
  return typeof data === "number" ? data : data.updated
}

export async function archiveNotification(id: string): Promise<AppNotification> {
  const response = await api.post<ApiEnvelope<AppNotification>>(
    `${BASE}/notifications/${id}/archive`,
    {},
  )

  return unwrap(response.data)
}

export async function archiveReadNotifications(): Promise<number> {
  const response = await api.post<ApiEnvelope<{ updated: number } | number>>(
    `${BASE}/notifications/archive-read`,
    {},
  )

  const data = unwrap(response.data)
  return typeof data === "number" ? data : data.updated
}

export async function getPreferences(): Promise<NotificationPreference[]> {
  const response = await api.get<ApiEnvelope<NotificationPreference[]>>(
    `${BASE}/preferences`,
  )

  return unwrap(response.data)
}

export async function savePreferences(
  preferences: PreferenceEntry[],
): Promise<NotificationPreference[]> {
  const response = await api.put<ApiEnvelope<NotificationPreference[]>>(
    `${BASE}/preferences`,
    { preferences },
  )

  return unwrap(response.data)
}

export async function getDeliveries(query: DeliveryQuery): Promise<DeliveriesPage> {
  const params: Record<string, string | number | boolean> = {
    page: query.page,
    pageSize: query.pageSize,
  }

  if (query.status) params.status = query.status
  if (query.channel) params.channel = query.channel
  if (query.type) params.type = query.type
  if (query.search) params.search = query.search

  const response = await api.get<ApiEnvelope<Delivery[]>>(
    `${BASE}/admin/deliveries`,
    { params },
  )

  const items = unwrap(response.data)

  if (!response.data.pagination) {
    throw new Error("The API returned an unexpected response.")
  }

  return { items, pagination: response.data.pagination }
}

export async function getDelivery(id: string): Promise<Delivery> {
  const response = await api.get<ApiEnvelope<Delivery>>(
    `${BASE}/admin/deliveries/${id}`,
  )

  return unwrap(response.data)
}

export async function requeueDelivery(id: string): Promise<Delivery> {
  const response = await api.post<ApiEnvelope<Delivery>>(
    `${BASE}/admin/deliveries/${id}/requeue`,
    {},
  )

  return unwrap(response.data)
}

export async function getTemplates(): Promise<Template[]> {
  const response = await api.get<ApiEnvelope<Template[]>>(
    `${BASE}/admin/templates`,
  )

  return unwrap(response.data)
}

export async function getTemplate(id: string): Promise<Template> {
  const response = await api.get<ApiEnvelope<Template>>(
    `${BASE}/admin/templates/${id}`,
  )

  return unwrap(response.data)
}

export async function createTemplate(input: TemplateInput): Promise<Template> {
  const response = await api.post<ApiEnvelope<Template>>(
    `${BASE}/admin/templates`,
    input,
  )

  return unwrap(response.data)
}

export async function updateTemplate(
  id: string,
  input: TemplateInput,
): Promise<Template> {
  const response = await api.put<ApiEnvelope<Template>>(
    `${BASE}/admin/templates/${id}`,
    input,
  )

  return unwrap(response.data)
}

export async function deleteTemplate(id: string): Promise<void> {
  await api.delete(`${BASE}/admin/templates/${id}`)
}

export async function getSettings(): Promise<Setting[]> {
  const response = await api.get<ApiEnvelope<Setting[]>>(
    `${BASE}/admin/settings`,
  )

  return unwrap(response.data)
}

export async function updateSetting(key: string, value: string): Promise<Setting> {
  const response = await api.put<ApiEnvelope<Setting>>(
    `${BASE}/admin/settings/${encodeURIComponent(key)}`,
    { value },
  )

  return unwrap(response.data)
}

export function formatRelativeTime(iso: string, now: Date = new Date()): string {
  const diffSeconds = Math.round((new Date(iso).getTime() - now.getTime()) / 1000)
  const absolute = Math.abs(diffSeconds)
  const formatter = new Intl.RelativeTimeFormat("en", { numeric: "auto" })

  if (absolute < 60) return formatter.format(diffSeconds, "second")
  if (absolute < 60 * 60) {
    return formatter.format(Math.round(diffSeconds / 60), "minute")
  }
  if (absolute < 24 * 60 * 60) {
    return formatter.format(Math.round(diffSeconds / (60 * 60)), "hour")
  }

  return formatter.format(Math.round(diffSeconds / (24 * 60 * 60)), "day")
}

const SEVERITY_LABELS: Record<NotificationSeverity, string> = {
  info: "Info",
  success: "Success",
  warning: "Warning",
  critical: "Critical",
}

const STATUS_LABELS: Record<NotificationStatus, string> = {
  unread: "Unread",
  read: "Read",
  archived: "Archived",
}

export function severityLabel(severity: NotificationSeverity): string {
  return SEVERITY_LABELS[severity] ?? severity
}

export function statusLabel(status: NotificationStatus): string {
  return STATUS_LABELS[status] ?? status
}

export default getNotifications
