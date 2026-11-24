import type {
  DeliveryQuery,
  DeliveryStatus,
  NotificationChannel,
  TemplateInput,
} from "@/services/notifications-api"

export interface TemplateFormErrors {
  type?: string
  locale?: string
  subject?: string
  bodyTemplate?: string
}

export interface DeliveryFilters {
  status: DeliveryStatus | "all"
  channel: NotificationChannel | "all"
  search: string
  page: number
  pageSize: number
}

const DELIVERY_STATUS_LABELS: Record<DeliveryStatus, string> = {
  queued: "Queued",
  sending: "Sending",
  sent: "Sent",
  retry_waiting: "Retry waiting",
  failed: "Failed",
}

export function deliveryStatusLabel(status: DeliveryStatus): string {
  return DELIVERY_STATUS_LABELS[status] ?? status
}

export function canRequeue(status: DeliveryStatus): boolean {
  return status === "failed"
}

export function buildDeliveryQuery(filters: DeliveryFilters): DeliveryQuery {
  const query: DeliveryQuery = {
    page: filters.page,
    pageSize: filters.pageSize,
  }

  if (filters.status !== "all") query.status = filters.status
  if (filters.channel !== "all") query.channel = filters.channel
  if (filters.search.trim()) query.search = filters.search.trim()

  return query
}

export function validateTemplate(input: TemplateInput): TemplateFormErrors {
  const errors: TemplateFormErrors = {}

  if (!input.type.trim()) errors.type = "Type is required."
  if (!input.locale.trim()) errors.locale = "Locale is required."
  if (!input.subject.trim()) errors.subject = "Subject is required."
  if (!input.bodyTemplate.trim()) errors.bodyTemplate = "Body is required."

  return errors
}

export function isNumericSettingKey(key: string): boolean {
  return /retention|attempt|backoff|limit/i.test(key)
}

export function validateSettingValue(key: string, value: string): string | null {
  if (!isNumericSettingKey(key)) return null

  if (!/^\d+$/.test(value.trim()) || Number(value) <= 0) {
    return "Enter a positive whole number."
  }

  return null
}
