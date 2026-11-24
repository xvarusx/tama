import { useEffect, useState } from "react"
import { RefreshCwIcon } from "lucide-react"

import { getAllocations, formatMoney, type PaymentAllocation } from "@/services/payments-api"
import { getApiErrorMessage } from "@/lib/api"
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

const COLUMN_COUNT = 5

export default function AllocationsPage() {
  const [items, setItems] = useState<PaymentAllocation[]>([])
  const [pageStatus, setPageStatus] = useState<"loading" | "error" | "ready">("loading")
  const [error, setError] = useState("")
  const [reloadKey, setReloadKey] = useState(0)

  useEffect(() => {
    let cancelled = false

    getAllocations()
      .then((result) => {
        if (cancelled) return
        setItems(result.items)
        setPageStatus("ready")
      })
      .catch((cause) => {
        if (cancelled) return
        setError(getApiErrorMessage(cause, "Unable to load allocations."))
        setPageStatus("error")
      })

    return () => {
      cancelled = true
    }
  }, [reloadKey])

  function refresh() {
    setPageStatus("loading")
    setError("")
    setReloadKey((key) => key + 1)
  }

  return (
    <div className="relative flex flex-1 flex-col gap-4 overflow-auto px-4 outline-none lg:px-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold">Allocations</h1>
          <p className="text-sm text-muted-foreground">
            View payment allocations to installments.
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
          <CardTitle>Allocations</CardTitle>
          <CardDescription>
            {items.length > 0
              ? `${items.length} allocation${items.length === 1 ? "" : "s"}`
              : "Loading allocations…"}
          </CardDescription>
        </CardHeader>

        <CardContent className="flex flex-col gap-4">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Payment</TableHead>
                <TableHead>Installment</TableHead>
                <TableHead>Invoice</TableHead>
                <TableHead className="text-right">Amount</TableHead>
                <TableHead className="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {pageStatus === "loading" && (
                <TableRow>
                  <td colSpan={COLUMN_COUNT} className="text-center text-muted-foreground">
                    Loading allocations…
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
                    No allocations yet.
                  </td>
                </TableRow>
              )}
              {pageStatus === "ready" &&
                items.map((allocation) => {
                  const { attributes } = allocation

                  return (
                    <TableRow key={allocation.id}>
                      <TableCell className="font-mono text-xs">
                        {attributes.paymentId.slice(0, 8)}…
                      </TableCell>
                      <TableCell className="font-mono text-xs">
                        {attributes.installmentId.slice(0, 8)}…
                      </TableCell>
                      <TableCell className="font-mono text-xs">
                        {attributes.invoiceId.slice(0, 8)}…
                      </TableCell>
                      <TableCell className="text-right font-mono tabular-nums">
                        {formatMoney(attributes.amount)}
                      </TableCell>
                      <TableCell className="text-right">
                        <a
                          href={`/dashboard/allocations/${allocation.id}`}
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
          <div className="text-sm text-muted-foreground">
            Total: {items.length} allocation{items.length === 1 ? "" : "s"}
          </div>
        </CardFooter>
      </Card>
    </div>
  )
}