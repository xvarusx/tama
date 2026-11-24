import { useEffect, useState } from "react"
import { RefreshCwIcon } from "lucide-react"

import {
  getPaymentPlans,
  formatMoney,
  type PaymentPlan,
  type PaymentPlanPagination,
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

export default function PaymentPlansPage() {
  const [page, setPage] = useState(1)
  const [pageSize, setPageSize] = useState(10)

  const [items, setItems] = useState<PaymentPlan[]>([])
  const [pagination, setPagination] = useState<PaymentPlanPagination | null>(null)
  const [status, setStatus] = useState<"loading" | "error" | "ready">("loading")
  const [error, setError] = useState("")
  const [reloadKey, setReloadKey] = useState(0)

  useEffect(() => {
    let cancelled = false

    getPaymentPlans({
      page,
      pageSize,
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
        setStatus("ready")
      })
      .catch((cause) => {
        if (cancelled) return

        setError(getApiErrorMessage(cause, "Unable to load payment plans."))
        setStatus("error")
      })

    return () => {
      cancelled = true
    }
  }, [page, pageSize, reloadKey])

  function beginLoading() {
    setStatus("loading")
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
          <h1 className="text-2xl font-bold">Payment plans</h1>
          <p className="text-sm text-muted-foreground">
            Manage installment plans for invoices.
          </p>
        </div>
        <div className="flex items-center gap-2">
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
          <CardTitle>Payment plans</CardTitle>
          <CardDescription>
            {pagination
              ? `${pagination.totalItems} plan${
                  pagination.totalItems === 1 ? "" : "s"
                }`
              : "Loading payment plans…"}
          </CardDescription>
        </CardHeader>

        <CardContent className="flex flex-col gap-4">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Invoice ID</TableHead>
                <TableHead className="text-right">Total amount</TableHead>
                <TableHead>Currency</TableHead>
                <TableHead className="text-right">Status</TableHead>
                <TableHead className="text-right">Installments</TableHead>
                <TableHead className="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {status === "loading" && (
                <TableRow>
                  <td colSpan={COLUMN_COUNT} className="text-center text-muted-foreground">
                    Loading payment plans…
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
                    No payment plans yet.
                  </td>
                </TableRow>
              )}
              {status === "ready" &&
                items.map((plan) => {
                  const { attributes } = plan

                  return (
                    <TableRow key={plan.id}>
                      <TableCell className="font-mono text-xs">
                        {attributes.invoiceId.slice(0, 8)}…
                      </TableCell>
                      <TableCell className="text-right font-mono tabular-nums">
                        {formatMoney(attributes.totalAmount, attributes.currency)}
                      </TableCell>
                      <TableCell>{attributes.currency}</TableCell>
                      <TableCell className="text-right">
                        <Badge variant="secondary">{attributes.status}</Badge>
                      </TableCell>
                      <TableCell className="text-right">
                        {attributes.installments.length}
                      </TableCell>
                      <TableCell className="text-right">
                        <a
                          href={`/dashboard/payment-plans/${plan.id}`}
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
