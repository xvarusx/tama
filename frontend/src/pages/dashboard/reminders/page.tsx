import { useEffect, useState } from "react"
import { RefreshCwIcon } from "lucide-react"

import {
  getReminders,
  type Reminder,
  type ReminderPagination,
  type ReminderKind,
} from "@/services/payments-api"
import { getApiErrorMessage } from "@/lib/api"
import TablePagination from "@/components/common/table-pagination"
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
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { Badge } from "@/components/ui/badge"

const COLUMN_COUNT = 6

export default function RemindersPage() {
  const [page, setPage] = useState(1)
  const [pageSize, setPageSize] = useState(10)
  const [kindFilter, setKindFilter] = useState<ReminderKind | "">("")

  const [items, setItems] = useState<Reminder[]>([])
  const [pagination, setPagination] = useState<ReminderPagination | null>(null)
  const [pageStatus, setPageStatus] = useState<"loading" | "error" | "ready">("loading")
  const [error, setError] = useState("")
  const [reloadKey, setReloadKey] = useState(0)

  useEffect(() => {
    let cancelled = false

    getReminders({
      page,
      pageSize,
      kind: kindFilter || undefined,
    })
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
        setPageStatus("ready")
      })
      .catch((cause) => {
        if (cancelled) return

        setError(getApiErrorMessage(cause, "Unable to load reminders."))
        setPageStatus("error")
      })

    return () => {
      cancelled = true
    }
  }, [page, pageSize, kindFilter, reloadKey])

  function beginLoading() {
    setPageStatus("loading")
    setError("")
  }

  function refresh() {
    beginLoading()
    setReloadKey((key) => key + 1)
  }

  const KIND_LABELS: Record<string, string> = {
    UPCOMING_DUE: "Upcoming due",
    OVERDUE: "Overdue",
  }

  const KIND_VARIANTS: Record<string, "default" | "outline" | "secondary" | "ghost" | "destructive" | "link"> = {
    UPCOMING_DUE: "secondary",
    OVERDUE: "destructive",
  }

  return (
    <div className="relative flex flex-1 flex-col gap-4 overflow-auto px-4 outline-none lg:px-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold">Reminders</h1>
          <p className="text-sm text-muted-foreground">
            View scheduled payment reminders.
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Button size="sm" onClick={refresh}>
            <RefreshCwIcon className="size-4" />
          </Button>
        </div>
      </div>

      {pageStatus === "error" && (
        <div className="flex flex-col gap-2 border border-destructive/40 bg-destructive/5 px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between">
          <span className="text-destructive">{error}</span>
          <Button variant="outline" size="sm" onClick={refresh}>
            Retry
          </Button>
        </div>
      )}

      <Card>
        <CardHeader>
          <CardTitle>Reminders</CardTitle>
          <CardDescription>
            {pagination
              ? `${pagination.totalItems} reminder${
                  pagination.totalItems === 1 ? "" : "s"
                }`
              : "Loading reminders…"}
          </CardDescription>
        </CardHeader>

        <CardContent className="flex flex-col gap-4">
          <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center w-full sm:w-auto">
              <Button
                size="sm"
                variant={kindFilter ? "default" : "outline"}
                onClick={() => setKindFilter("")}
              >
                All kinds
              </Button>
              {Object.entries(KIND_LABELS).map(([key, label]) => (
                <Button
                  key={key}
                  size="sm"
                  variant={kindFilter === key ? "default" : "outline"}
                  onClick={() => setKindFilter(key as ReminderKind)}
                >
                  {label}
                </Button>
              ))}
            </div>
          </div>

          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Installment</TableHead>
                <TableHead>Kind</TableHead>
                <TableHead className="text-right">Scheduled for</TableHead>
                <TableHead className="text-right">Status</TableHead>
                <TableHead>Sent at</TableHead>
                <TableHead className="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {pageStatus === "loading" && (
                <TableRow>
                  <td colSpan={COLUMN_COUNT} className="text-center text-muted-foreground">
                    Loading reminders…
                  </td>
                </TableRow>
              )}
              {pageStatus === "error" && (
                <TableRow>
                  <td colSpan={COLUMN_COUNT} className="text-center">
                    <span className="text-destructive">{error}</span>{" "}
                    <Button variant="outline" size="sm" onClick={refresh}>
                      Retry
                    </Button>
                  </td>
                </TableRow>
              )}
              {pageStatus === "ready" && items.length === 0 && (
                <TableRow>
                  <td colSpan={COLUMN_COUNT} className="text-center text-muted-foreground">
                    No reminders yet.
                  </td>
                </TableRow>
              )}
              {pageStatus === "ready" &&
                items.map((reminder) => {
                  const { attributes } = reminder

                  return (
                    <TableRow key={reminder.id}>
                      <TableCell className="font-mono text-xs">
                        {attributes.installmentId.slice(0, 8)}…
                      </TableCell>
                      <TableCell>
                        <Badge variant={KIND_VARIANTS[attributes.kind]}>
                          {KIND_LABELS[attributes.kind]}
                        </Badge>
                      </TableCell>
                      <TableCell className="text-right">
                        {attributes.scheduledFor}
                      </TableCell>
                      <TableCell className="text-right">
                        <Badge variant="secondary">{attributes.status}</Badge>
                      </TableCell>
                      <TableCell>{attributes.sentAt ?? "—"}</TableCell>
                      <TableCell className="text-right">
                        <a
                          href={`/dashboard/reminders/${reminder.id}`}
                          className="text-muted-foreground hover:underline"
                        >
                          View
                        </a>
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
