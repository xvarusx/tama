import type {
  NotificationQuery,
  NotificationSeverity,
  NotificationStatus,
} from "@/services/notifications-api"

export type InboxTab = "all" | "unread" | "archived"

export interface InboxFilters {
  tab: InboxTab
  severity: NotificationSeverity | ""
  search: string
  page: number
  pageSize: number
}

const SEVERITY_CLASSES: Record<NotificationSeverity, string> = {
  info: "text-sky-600 dark:text-sky-400",
  success: "text-emerald-600 dark:text-emerald-400",
  warning: "text-amber-600 dark:text-amber-400",
  critical: "text-destructive",
}

const SEVERITY_BADGE_VARIANTS: Record<NotificationSeverity, string> = {
  info: "secondary",
  success: "secondary",
  warning: "destructive",
  critical: "destructive",
}

export function severityClass(severity: NotificationSeverity): string {
  return SEVERITY_CLASSES[severity] ?? SEVERITY_CLASSES.info
}

export function severityBadgeVariant(severity: NotificationSeverity): string {
  return SEVERITY_BADGE_VARIANTS[severity] ?? "secondary"
}

export function tabToStatus(tab: InboxTab): NotificationStatus | undefined {
  if (tab === "unread") return "unread"
  if (tab === "archived") return "archived"
  return undefined
}

export function buildInboxQuery(filters: InboxFilters): NotificationQuery {
  const query: NotificationQuery = {
    page: filters.page,
    pageSize: filters.pageSize,
  }

  const status = tabToStatus(filters.tab)
  if (status) query.status = status
  if (filters.severity) query.severity = filters.severity
  if (filters.search.trim()) query.search = filters.search.trim()

  return query
}
