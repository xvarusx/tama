import { useEffect, useState } from "react"
import { RefreshCwIcon, SearchIcon } from "lucide-react"
import { Link } from "react-router-dom"

import getPayments, {
  formatMoney,
  type Payment,
  type PaymentPagination,
  type PaymentStatus,
  type PaymentMethod,
  PAYMENT_STATUS_LABELS,
  PAYMENT_STATUS_VARIANTS,
  PAYMENT_METHOD_LABELS,
  PAYMENT_DIRECTION_LABELS,
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
  InputGroup,
  InputGroupAddon,
  InputGroupInput,
} from "@/components/ui/input-group"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { Badge } from "@/components/ui/badge"

const COLUMN_COUNT = 8

export default function PaymentsPage() {
  const [page, setPage] = useState(1)
  const [pageSize, setPageSize] = useState(10)
  const [searchInput, setSearchInput] = useState("")
  const [search, setSearch] = useState("")
  const [statusFilter, setStatusFilter] = useState<PaymentStatus | "">("")
  const [methodFilter, setMethodFilter] = useState<PaymentMethod | "">("")

  const [items, setItems] = useState<Payment[]>([])
  const [pagination, setPagination] = useState<PaymentPagination | null>(null)
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

    getPayments({
      page,
      pageSize,
      search: search || undefined,
      status: statusFilter || undefined,
      method: methodFilter || undefined,
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

        setError(getApiErrorMessage(cause, "Unable to load payments."))
        setStatus("error")
      })

    return () => {
      cancelled = true
    }
  }, [page, pageSize, search, statusFilter, methodFilter, reloadKey])

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
          <h1 className="text-2xl font-bold">Payments</h1>
          <p className="text-sm text-muted-foreground">
            Track incoming and outgoing payments.
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
          <CardTitle>Payments</CardTitle>
          <CardDescription>
            {pagination
              ? `${pagination.totalItems} payment${
                  pagination.totalItems === 1 ? "" : "s"
                }`
              : "Loading payments…"}
          </CardDescription>
        </CardHeader>

        <CardContent className="flex flex-col gap-4">
          <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center w-full sm:w-auto">
              <InputGroup className="sm:max-w-sm">
                <InputGroupAddon>
                  <SearchIcon />
                </InputGroupAddon>
                <InputGroupInput
                  placeholder="Search by number or party..."
                  value={searchInput}
                  onChange={(event) => setSearchInput(event.target.value)}
                  aria-label="Search payments"
                />
              </InputGroup>

              <Button
                size="sm"
                variant={statusFilter ? "default" : "outline"}
                onClick={() => setStatusFilter("")}
              >
                All statuses
              </Button>
              {Object.entries(PAYMENT_STATUS_LABELS).map(([key, label]) => (
                <Button
                  key={key}
                  size="sm"
                  variant={statusFilter === key ? "default" : "outline"}
                  onClick={() => setStatusFilter(key as PaymentStatus)}
                >
                  {label}
                </Button>
              ))}

              <Button
                size="sm"
                variant={methodFilter ? "default" : "outline"}
                onClick={() => setMethodFilter("")}
              >
                All methods
              </Button>
              {Object.entries(PAYMENT_METHOD_LABELS).map(([key, label]) => (
                <Button
                  key={key}
                  size="sm"
                  variant={methodFilter === key ? "default" : "outline"}
                  onClick={() => setMethodFilter(key as PaymentMethod)}
                >
                  {label}
                </Button>
              ))}
            </div>
          </div>

          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Number</TableHead>
                <TableHead>Direction</TableHead>
                <TableHead>Method</TableHead>
                <TableHead className="text-right">Amount</TableHead>
                <TableHead className="text-right">Status</TableHead>
                <TableHead className="text-right">Party</TableHead>
                <TableHead>Reference</TableHead>
                <TableHead className="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {status === "loading" && (
                <TableRow>
                  <td colSpan={COLUMN_COUNT} className="text-center text-muted-foreground">
                    Loading payments…
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
                    No payments yet.
                  </td>
                </TableRow>
              )}
              {status === "ready" &&
                items.map((payment) => {
                  const { attributes } = payment

                  return (
                    <TableRow key={payment.id}>
                      <TableCell>
                        <Link to={`/dashboard/payments/${payment.id}`}>
                          {attributes.number}
                        </Link>
                      </TableCell>
                      <TableCell>
                        <Badge variant="secondary">{PAYMENT_DIRECTION_LABELS[attributes.direction]}</Badge>
                      </TableCell>
                      <TableCell>
                        <Badge variant="secondary">{PAYMENT_METHOD_LABELS[attributes.method]}</Badge>
                      </TableCell>
                      <TableCell className="text-right font-mono tabular-nums">
                        {formatMoney(attributes.amount, attributes.currency)}
                      </TableCell>
                      <TableCell>
                        <Badge variant={PAYMENT_STATUS_VARIANTS[attributes.status]}>
                          {PAYMENT_STATUS_LABELS[attributes.status]}
                        </Badge>
                      </TableCell>
                      <TableCell>{attributes.partyName}</TableCell>
                      <TableCell>{attributes.reference ?? "—"}</TableCell>
                      <TableCell className="text-right">
                        <Link to={`/dashboard/payments/${payment.id}`}>
                          View
                        </Link>
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
