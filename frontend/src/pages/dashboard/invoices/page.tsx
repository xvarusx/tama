import { useEffect, useState } from "react"
import { RefreshCwIcon, SearchIcon } from "lucide-react"
import { Link } from "react-router-dom"

import getInvoices, {
  formatMoney,
  type Invoice,
  type InvoicePagination,
  type InvoiceStatus,
} from "@/services/invoices-api"
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

const STATUS_LABELS: Record<InvoiceStatus, string> = {
  draft: "Draft",
  issued: "Issued",
  partially_paid: "Partially paid",
  paid: "Paid",
  voided: "Voided",
}

const STATUS_VARIANTS: Record<InvoiceStatus, string> = {
  draft: "secondary",
  issued: "default",
  partially_paid: "warning",
  paid: "success",
  voided: "destructive",
}

export default function InvoicesPage() {
  const [page, setPage] = useState(1)
  const [pageSize, setPageSize] = useState(10)
  const [searchInput, setSearchInput] = useState("")
  const [search, setSearch] = useState("")
  const [statusFilter, setStatusFilter] = useState<InvoiceStatus | "">("")

  const [items, setItems] = useState<Invoice[]>([])
  const [pagination, setPagination] = useState<InvoicePagination | null>(null)
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

    getInvoices({
      page,
      pageSize,
      search: search || undefined,
      status: statusFilter || undefined,
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

        setError(getApiErrorMessage(cause, "Unable to load invoices."))
        setStatus("error")
      })

    return () => {
      cancelled = true
    }
  }, [page, pageSize, search, statusFilter, reloadKey])

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
          <h1 className="text-2xl font-bold">Invoices</h1>
          <p className="text-sm text-muted-foreground">
            View and manage invoices, payments and credit notes.
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
          <CardTitle>Invoices</CardTitle>
          <CardDescription>
            {pagination
              ? `${pagination.totalItems} invoice${
                  pagination.totalItems === 1 ? "" : "s"
                }`
              : "Loading invoices…"}
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
                  aria-label="Search invoices"
                />
              </InputGroup>

              <Button
                size="sm"
                variant={statusFilter ? "default" : "outline"}
                onClick={() => setStatusFilter("")}
              >
                All statuses
              </Button>
              {Object.entries(STATUS_LABELS).map(([key, label]) => (
                <Button
                  key={key}
                  size="sm"
                  variant={
                    statusFilter === key ? "default" : "outline"
                  }
                  onClick={() => setStatusFilter(key as InvoiceStatus)}
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
                <TableHead>Status</TableHead>
                <TableHead>Type</TableHead>
                <TableHead className="text-right">Total</TableHead>
                <TableHead className="text-right">Balance</TableHead>
                <TableHead className="text-right">Due date</TableHead>
                <TableHead className="text-right">Party</TableHead>
                <TableHead className="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {status === "loading" && (
                <TableRow>
                  <td colSpan={COLUMN_COUNT} className="text-center text-muted-foreground">
                    Loading invoices…
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
                    No invoices yet.
                  </td>
                </TableRow>
              )}
              {status === "ready" &&
                items.map((invoice) => {
                  const { attributes } = invoice

                  return (
                    <TableRow key={invoice.id}>
                      <TableCell>
                        <Link to={`/dashboard/invoices/${invoice.id}`}>
                          {attributes.number ?? "—"}
                        </Link>
                      </TableCell>
                      <TableCell>
                        <Badge variant={STATUS_VARIANTS[attributes.status]}>
                          {STATUS_LABELS[attributes.status]}
                        </Badge>
                      </TableCell>
                      <TableCell>{attributes.type}</TableCell>
                      <TableCell className="text-right font-mono tabular-nums">
                        {formatMoney(attributes.total, attributes.currency)}
                      </TableCell>
                      <TableCell className="text-right font-mono tabular-nums">
                        {formatMoney(attributes.balance, attributes.currency)}
                      </TableCell>
                      <TableCell className="text-right">
                        {attributes.dueDate ?? "—"}
                      </TableCell>
                      <TableCell>
                        {attributes.partyName}{" "}
                        ({attributes.partyId.slice(0, 8)}…)
                      </TableCell>
                      <TableCell className="text-right">
                        <Link to={`/dashboard/invoices/${invoice.id}`}>
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
