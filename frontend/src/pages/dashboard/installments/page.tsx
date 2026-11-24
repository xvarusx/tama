import { useEffect, useState } from "react"
import { RefreshCwIcon } from "lucide-react"

import {
  getInstallments,
  formatMoney,
  type PaymentPlanInstallment,
  type PaymentPlanPagination,
  type InstallmentStatus,
  INSTALLMENT_STATUS_LABELS,
  INSTALLMENT_STATUS_VARIANTS,
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

export default function InstallmentsPage() {
  const [page, setPage] = useState(1)
  const [pageSize, setPageSize] = useState(10)
  const [statusFilter, setStatusFilter] = useState<InstallmentStatus | "">("")
  const [overdueOnly, setOverdueOnly] = useState(false)

  const [items, setItems] = useState<PaymentPlanInstallment[]>([])
  const [pagination, setPagination] = useState<PaymentPlanPagination | null>(null)
  const [pageStatus, setPageStatus] = useState<"loading" | "error" | "ready">("loading")
  const [error, setError] = useState("")
  const [reloadKey, setReloadKey] = useState(0)

  useEffect(() => {
    let cancelled = false

    getInstallments({
      page,
      pageSize,
      status: statusFilter || undefined,
      overdueOnly,
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

        setError(getApiErrorMessage(cause, "Unable to load installments."))
        setPageStatus("error")
      })

    return () => {
      cancelled = true
    }
  }, [page, pageSize, statusFilter, overdueOnly, reloadKey])

  function beginLoading() {
    setPageStatus("loading")
    setError("")
  }

  function refresh() {
    beginLoading()
    setReloadKey((key) => key + 1)
  }

  return (
    <div className="relative flex flex-1 flex-col gap-4 overflow-auto px-4 outline-none lg:px-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold">Installments</h1>
          <p className="text-sm text-muted-foreground">
            Track scheduled installment payments.
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
          <CardTitle>Installments</CardTitle>
          <CardDescription>
            {pagination
              ? `${pagination.totalItems} installment${
                  pagination.totalItems === 1 ? "" : "s"
                }`
              : "Loading installments…"}
          </CardDescription>
        </CardHeader>

        <CardContent className="flex flex-col gap-4">
          <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center w-full sm:w-auto">
              <Button
                size="sm"
                variant={statusFilter ? "default" : "outline"}
                onClick={() => setStatusFilter("")}
              >
                All statuses
              </Button>
              {Object.entries(INSTALLMENT_STATUS_LABELS).map(([key, label]) => (
                <Button
                  key={key}
                  size="sm"
                  variant={statusFilter === key ? "default" : "outline"}
                  onClick={() => setStatusFilter(key as InstallmentStatus)}
                >
                  {label}
                </Button>
              ))}

              <Button
                size="sm"
                variant={overdueOnly ? "default" : "outline"}
                onClick={() => setOverdueOnly(!overdueOnly)}
              >
                {overdueOnly ? "Show all" : "Overdue only"}
              </Button>
            </div>
          </div>

          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Sequence</TableHead>
                <TableHead>Due date</TableHead>
                <TableHead className="text-right">Amount</TableHead>
                <TableHead className="text-right">Paid</TableHead>
                <TableHead className="text-right">Remaining</TableHead>
                <TableHead className="text-right">Status</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {pageStatus === "loading" && (
                <TableRow>
                  <td colSpan={COLUMN_COUNT} className="text-center text-muted-foreground">
                    Loading installments…
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
                    No installments yet.
                  </td>
                </TableRow>
              )}
              {pageStatus === "ready" &&
                items.map((installment) => {
                  const { attributes } = installment

                  return (
                    <TableRow key={installment.id}>
                      <TableCell className="font-mono text-xs">
                        #{attributes.sequence}
                      </TableCell>
                      <TableCell>{attributes.dueDate}</TableCell>
                      <TableCell className="text-right font-mono tabular-nums">
                        {formatMoney(attributes.amount, "USD")}
                      </TableCell>
                      <TableCell className="text-right font-mono tabular-nums">
                        {formatMoney(attributes.paidAmount, "USD")}
                      </TableCell>
                      <TableCell className="text-right font-mono tabular-nums">
                        {formatMoney(attributes.remaining, "USD")}
                      </TableCell>
                      <TableCell>
                        <Badge variant={INSTALLMENT_STATUS_VARIANTS[attributes.status]}>
                          {INSTALLMENT_STATUS_LABELS[attributes.status]}
                        </Badge>
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
