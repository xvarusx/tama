import { useCallback, useEffect, useState } from "react"
import { Link, useLocation, useNavigate } from "react-router-dom"
import { BellIcon } from "lucide-react"

import {
  formatRelativeTime,
  getNotifications,
  getUnreadCount,
  markNotificationRead,
  severityLabel,
  type AppNotification,
} from "@/services/notifications-api"
import { severityClass } from "@/lib/notifications"
import { Button } from "@/components/ui/button"
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover"
const POLL_INTERVAL_MS = 60_000

export function NotificationBell() {
  const { pathname } = useLocation()
  const navigate = useNavigate()

  const [count, setCount] = useState(0)
  const [items, setItems] = useState<AppNotification[]>([])
  const [open, setOpen] = useState(false)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState("")

  const refreshCount = useCallback(() => {
    getUnreadCount()
      .then(setCount)
      .catch(() => {
        // The bell stays silent when the service is unavailable.
      })
  }, [])

  useEffect(() => {
    refreshCount()
    const timer = window.setInterval(refreshCount, POLL_INTERVAL_MS)
    return () => window.clearInterval(timer)
  }, [refreshCount, pathname])

  function handleOpenChange(nextOpen: boolean) {
    setOpen(nextOpen)
    if (nextOpen) {
      setLoading(true)
      setError("")
    }
  }

  useEffect(() => {
    if (!open) return

    let cancelled = false

    getNotifications({ page: 1, pageSize: 5, status: "unread" })
      .then((result) => {
        if (cancelled) return
        setItems(result.items)
        setCount(result.unreadCount)
        setLoading(false)
      })
      .catch(() => {
        if (cancelled) return
        setError("Unable to load notifications.")
        setLoading(false)
      })

    return () => {
      cancelled = true
    }
  }, [open, pathname])

  async function handleItemClick(item: AppNotification) {
    try {
      await markNotificationRead(item.id)
    } catch {
      // Still navigate even when marking read fails.
    }

    setCount((current) => Math.max(0, current - 1))
    setItems((current) => current.filter((entry) => entry.id !== item.id))
    setOpen(false)
    navigate(item.attributes.link ?? "/dashboard/notifications")
  }

  return (
    <Popover open={open} onOpenChange={handleOpenChange}>
      <PopoverTrigger
        render={
          <Button
            variant="ghost"
            size="icon"
            className="relative"
            aria-label={
              count > 0
                ? `Notifications, ${count} unread`
                : "Notifications, no unread"
            }
          />
        }
      >
        <BellIcon className="size-4" />
        {count > 0 && (
          <span className="absolute -top-1 -right-1 flex min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[0.625rem] leading-4 font-semibold text-destructive-foreground">
            {count > 9 ? "9+" : count}
          </span>
        )}
      </PopoverTrigger>
      <PopoverContent align="end" className="w-80 p-0">
        <div className="flex items-center justify-between border-b px-4 py-3">
          <span className="text-sm font-semibold">Notifications</span>
          <span className="text-xs text-muted-foreground">{count} unread</span>
        </div>
        <div className="max-h-80 overflow-auto">
          {loading && (
            <p className="px-4 py-6 text-center text-sm text-muted-foreground">
              Loading…
            </p>
          )}
          {!loading && error && (
            <p className="px-4 py-6 text-center text-sm text-destructive">
              {error}
            </p>
          )}
          {!loading && !error && items.length === 0 && (
            <p className="px-4 py-6 text-center text-sm text-muted-foreground">
              You're all caught up.
            </p>
          )}
          {!loading &&
            !error &&
            items.map((item) => (
              <button
                key={item.id}
                type="button"
                className="flex w-full flex-col gap-1 border-b px-4 py-3 text-left last:border-b-0 hover:bg-muted/50"
                onClick={() => handleItemClick(item)}
              >
                <span className="flex items-center justify-between gap-2">
                  <span className="truncate text-sm font-medium">
                    {item.attributes.title}
                  </span>
                  <span className={severityClass(item.attributes.severity)}>
                    {severityLabel(item.attributes.severity)}
                  </span>
                </span>
                <span className="line-clamp-2 text-xs text-muted-foreground">
                  {item.attributes.body}
                </span>
                <span className="text-[0.625rem] text-muted-foreground">
                  {formatRelativeTime(item.attributes.createdAt)}
                </span>
              </button>
            ))}
        </div>
        <div className="border-t p-2">
          <Button variant="ghost" size="sm" className="w-full" nativeButton={false} render={<Link to="/dashboard/notifications" />}>
            View all
          </Button>
        </div>
      </PopoverContent>
    </Popover>
  )
}
