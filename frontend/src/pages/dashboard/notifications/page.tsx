import { useEffect, useState } from "react"
import { Link, useNavigate } from "react-router-dom"
import {
  ArchiveIcon,
  CheckCheckIcon,
  InboxIcon,
  RefreshCwIcon,
  SearchIcon,
  Settings2Icon,
} from "lucide-react"

import {
  formatRelativeTime,
  getNotifications,
  archiveNotification,
  archiveReadNotifications,
  markAllNotificationsRead,
  markNotificationRead,
  severityLabel,
  statusLabel,
  type AppNotification,
  type NotificationPagination,
  type NotificationSeverity,
} from "@/services/notifications-api"
import { getApiErrorMessage } from "@/lib/api"
import { useAuth } from "@/lib/auth-context"
import { buildInboxQuery, severityClass, type InboxTab } from "@/lib/notifications"
import TablePagination from "@/components/common/table-pagination"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import {
  InputGroup,
  InputGroupAddon,
  InputGroupInput,
} from "@/components/ui/input-group"
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { toast } from "@/components/ui/toast"

const COLUMN_COUNT = 6

const SEVERITIES: NotificationSeverity[] = [
  "info",
  "success",
  "warning",
  "critical",
]

export default function NotificationsPage() {
  const navigate = useNavigate()
  const { user } = useAuth()
  const isAdmin = user?.roles.includes("ROLE_ADMIN") ?? false

  const [tab, setTab] = useState<InboxTab>("all")
  const [severityFilter, setSeverityFilter] = useState<NotificationSeverity | "all">(
    "all",
  )
  const [searchInput, setSearchInput] = useState("")
  const [search, setSearch] = useState("")
  const [page, setPage] = useState(1)
  const [pageSize, setPageSize] = useState(10)

  const [items, setItems] = useState<AppNotification[]>([])
  const [pagination, setPagination] = useState<NotificationPagination | null>(null)
  const [unreadCount, setUnreadCount] = useState(0)
  const [status, setStatus] = useState<"loading" | "error" | "ready">("loading")
  const [error, setError] = useState("")
  const [reloadKey, setReloadKey] = useState(0)

  useEffect(() => {
    const nextSearch = searchInput.trim()
    if (nextSearch === search) return

    const timer = window.setTimeout(() => {
      setStatus("loading")
      setSearch(nextSearch)
      setPage(1)
    }, 300)

    return () => window.clearTimeout(timer)
  }, [searchInput, search])

  useEffect(() => {
    let cancelled = false

    getNotifications(
      buildInboxQuery({
        tab,
        severity: severityFilter === "all" ? "" : severityFilter,
        search,
        page,
        pageSize,
      }),
    )
      .then((result) => {
        if (cancelled) return

        if (
          result.pagination.totalPages >= 1 &&
          page > result.pagination.totalPages
        ) {
          setPage(result.pagination.totalPages)
          return
        }

        setItems(result.items)
        setPagination(result.pagination)
        setUnreadCount(result.unreadCount)
        setStatus("ready")
      })
      .catch((cause) => {
        if (cancelled) return
        setError(getApiErrorMessage(cause, "Unable to load notifications."))
        setStatus("error")
      })

    return () => {
      cancelled = true
    }
  }, [tab, severityFilter, search, page, pageSize, reloadKey])

  function beginLoading() {
    setStatus("loading")
    setError("")
  }

  function refresh() {
    beginLoading()
    setReloadKey((key) => key + 1)
  }

  async function runAction(
    action: () => Promise<unknown>,
    successMessage: string,
  ) {
    try {
      await action()
      toast.add({ title: successMessage, type: "success" })
      refresh()
    } catch (cause) {
      toast.add({
        title: "Action failed",
        description: getApiErrorMessage(cause),
        type: "error",
      })
    }
  }

  async function handleOpen(item: AppNotification) {
    if (item.attributes.status === "unread") {
      try {
        await markNotificationRead(item.id)
      } catch {
        // Follow the link even if marking read fails.
      }
    }

    if (item.attributes.link) {
      navigate(item.attributes.link)
      return
    }

    refresh()
  }

  function handleTabChange(value: string) {
    setTab(value as InboxTab)
    setPage(1)
    beginLoading()
  }

  return (
    <div className="relative flex flex-1 flex-col gap-4 overflow-auto px-4 outline-none lg:px-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold">Notifications</h1>
          <p className="text-sm text-muted-foreground">
            Review alerts, preferences and delivery status.
          </p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          {isAdmin && (
            <Button
              size="sm"
              variant="outline"
              nativeButton={false}
              render={<Link to="/dashboard/notifications/admin" />}
            >
              <Settings2Icon className="size-4" />
              Admin
            </Button>
          )}
          <Button
            size="sm"
            variant="outline"
            nativeButton={false}
            render={<Link to="/dashboard/notifications/preferences" />}
          >
            Preferences
          </Button>
          <Button
            size="sm"
            variant="outline"
            onClick={() =>
              runAction(markAllNotificationsRead, "All notifications marked read.")
            }
          >
            <CheckCheckIcon className="size-4" />
            Mark all read
          </Button>
          <Button
            size="sm"
            variant="outline"
            onClick={() =>
              runAction(archiveReadNotifications, "Read notifications archived.")
            }
          >
            <ArchiveIcon className="size-4" />
            Archive read
          </Button>
          <Button size="sm" onClick={refresh}>
            <RefreshCwIcon className="size-4" />
          </Button>
        </div>
      </div>

      {status === "error" && (
        <div className="flex flex-col gap-2 border border-destructive/40 bg-destructive/5 px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between">
          <span className="text-destructive">{error}</span>
          <Button variant="outline" size="sm" onClick={refresh}>
            Retry
          </Button>
        </div>
      )}

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <InboxIcon className="size-5" />
            Inbox
            {unreadCount > 0 && <Badge>{unreadCount} unread</Badge>}
          </CardTitle>
          <CardDescription>
            {pagination
              ? `${pagination.totalItems} notification${
                  pagination.totalItems === 1 ? "" : "s"
                }`
              : "Loading notifications…"}
          </CardDescription>
        </CardHeader>

        <CardContent className="flex flex-col gap-4">
          <Tabs value={tab} onValueChange={handleTabChange}>
            <TabsList>
              <TabsTrigger value="all">All</TabsTrigger>
              <TabsTrigger value="unread">Unread</TabsTrigger>
              <TabsTrigger value="archived">Archived</TabsTrigger>
            </TabsList>
          </Tabs>

          <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <InputGroup className="sm:max-w-sm">
              <InputGroupAddon>
                <SearchIcon />
              </InputGroupAddon>
              <InputGroupInput
                placeholder="Search notifications..."
                value={searchInput}
                onChange={(event) => setSearchInput(event.target.value)}
                aria-label="Search notifications"
              />
            </InputGroup>

            <Select
              value={severityFilter}
              onValueChange={(value) => {
                setSeverityFilter((value as NotificationSeverity | "all") ?? "all")
                setPage(1)
                beginLoading()
              }}
            >
              <SelectTrigger className="w-44" aria-label="Filter by severity">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectGroup>
                  <SelectItem value="all">All severities</SelectItem>
                  {SEVERITIES.map((severity) => (
                    <SelectItem key={severity} value={severity}>
                      {severityLabel(severity)}
                    </SelectItem>
                  ))}
                </SelectGroup>
              </SelectContent>
            </Select>
          </div>

          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Title</TableHead>
                <TableHead>Severity</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Type</TableHead>
                <TableHead>Received</TableHead>
                <TableHead className="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {status === "loading" && (
                <TableRow>
                  <td colSpan={COLUMN_COUNT} className="text-center text-muted-foreground">
                    Loading notifications…
                  </td>
                </TableRow>
              )}
              {status === "error" && (
                <TableRow>
                  <td colSpan={COLUMN_COUNT} className="text-center">
                    <span className="text-destructive">{error}</span>{" "}
                    <Button variant="outline" size="sm" onClick={refresh}>
                      Retry
                    </Button>
                  </td>
                </TableRow>
              )}
              {status === "ready" && items.length === 0 && (
                <TableRow>
                  <td colSpan={COLUMN_COUNT} className="text-center text-muted-foreground">
                    No notifications found.
                  </td>
                </TableRow>
              )}
              {status === "ready" &&
                items.map((item) => {
                  const attributes = item.attributes
                  return (
                    <TableRow key={item.id}>
                      <TableCell>
                        <button
                          type="button"
                          className="text-left font-medium hover:underline"
                          onClick={() => handleOpen(item)}
                        >
                          {attributes.title}
                        </button>
                      </TableCell>
                      <TableCell>
                        <span className={severityClass(attributes.severity)}>
                          {severityLabel(attributes.severity)}
                        </span>
                      </TableCell>
                      <TableCell>
                        <Badge variant={attributes.status === "unread" ? "default" : "secondary"}>
                          {statusLabel(attributes.status)}
                        </Badge>
                      </TableCell>
                      <TableCell className="text-muted-foreground">
                        {attributes.type}
                      </TableCell>
                      <TableCell className="text-muted-foreground">
                        {formatRelativeTime(attributes.createdAt)}
                      </TableCell>
                      <TableCell className="text-right">
                        <div className="flex justify-end gap-1">
                          {attributes.status === "unread" && (
                            <Button
                              variant="ghost"
                              size="sm"
                              onClick={() =>
                                runAction(
                                  () => markNotificationRead(item.id),
                                  "Notification marked read.",
                                )
                              }
                            >
                              Read
                            </Button>
                          )}
                          {attributes.status !== "archived" && (
                            <Button
                              variant="ghost"
                              size="sm"
                              onClick={() =>
                                runAction(
                                  () => archiveNotification(item.id),
                                  "Notification archived.",
                                )
                              }
                            >
                              Archive
                            </Button>
                          )}
                        </div>
                      </TableCell>
                    </TableRow>
                  )
                })}
            </TableBody>
          </Table>
        </CardContent>

        <CardFooter>
          <TablePagination
            page={page}
            limit={pageSize}
            total={pagination?.totalItems ?? 0}
            onPageChange={(nextPage) => {
              beginLoading()
              setPage(nextPage)
            }}
            onLimitChange={(limit) => {
              beginLoading()
              setPageSize(limit)
              setPage(1)
            }}
          />
        </CardFooter>
      </Card>
    </div>
  )
}
