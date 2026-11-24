import { useEffect, useState } from "react"
import { ArrowLeftIcon, RefreshCwIcon } from "lucide-react"
import { useNavigate, useParams } from "react-router-dom"

import {
  getPayment,
  recordPayment,
  failPayment,
  reversePayment,
  cancelPayment,
  formatMoney,
  type Payment,
  PAYMENT_STATUS_LABELS,
  PAYMENT_STATUS_VARIANTS,
  PAYMENT_METHOD_LABELS,
  PAYMENT_DIRECTION_LABELS,
} from "@/services/payments-api"
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
import { Badge } from "@/components/ui/badge"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { Skeleton } from "@/components/ui/skeleton"

type Status = "loading" | "error" | "ready"

function Detail({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex flex-col gap-1 border-b py-2 last:border-b-0">
      <span className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
        {label}
      </span>
      <span>{value}</span>
    </div>
  )
}

export default function PaymentShowPage() {
  const { id } = useParams()
  const navigate = useNavigate()

  const [payment, setPayment] = useState<Payment | null>(null)
  const [status, setStatus] = useState<Status>("loading")
  const [error, setError] = useState("")
  const [reloadKey, setReloadKey] = useState(0)

  useEffect(() => {
    let cancelled = false

    getPayment(id)
      .then((nextPayment) => {
        if (cancelled) return
        setPayment(nextPayment)
        setStatus("ready")
      })
      .catch((cause) => {
        if (cancelled) return
        setError(getApiErrorMessage(cause, "Unable to load the payment."))
        setStatus("error")
      })

    return () => {
      cancelled = true
    }
  }, [id, reloadKey])

  function reload() {
    setStatus("loading")
    setError("")
    setReloadKey((key) => key + 1)
  }

  async function handleAction(action: "record" | "fail" | "reverse" | "cancel") {
    if (!payment) return

    try {
      let updated: Payment
      if (action === "record") {
        updated = await recordPayment(payment.id)
      } else if (action === "fail") {
        const reason = window.prompt(`Fail payment ${payment.number}? Reason:`)
        if (reason === null || !reason.trim()) return
        updated = await failPayment(payment.id, reason.trim())
      } else if (action === "reverse") {
        const reason = window.prompt(
          `Reverse payment ${payment.number}? Reason:`,
        )
        if (reason === null || !reason.trim()) return
        updated = await reversePayment(payment.id, reason.trim())
      } else {
        if (
          !window.confirm(`Cancel payment ${payment.number}? This cannot be undone.`)
        ) {
          return
        }
        updated = await cancelPayment(payment.id)
      }

      setPayment(updated)
      setStatus("ready")
    } catch (cause) {
      setError(getApiErrorMessage(cause, "Unable to update payment."))
    }
  }

  if (!id) {
    return (
      <div className="flex flex-1 flex-col items-start gap-4 px-4 lg:px-6">
        <Button variant="outline" onClick={() => navigate("/dashboard/payments")}>
          <ArrowLeftIcon />
          Back to payments
        </Button>
        <p className="text-destructive">Invalid payment id.</p>
      </div>
    )
  }

  if (status === "loading") {
    return (
      <div className="flex flex-1 flex-col gap-4 px-4 lg:px-6">
        <Skeleton className="h-8 w-64" />
        <Skeleton className="h-96" />
      </div>
    )
  }

  if (status === "error" || !payment) {
    return (
      <div className="flex flex-1 flex-col items-start gap-4 px-4 lg:px-6">
        <Button variant="outline" onClick={() => navigate("/dashboard/payments")}>
          <ArrowLeftIcon />
          Back to payments
        </Button>
        <p className="text-destructive">{error}</p>
      </div>
    )
  }

  const { attributes } = payment

  return (
    <div className="flex flex-1 flex-col gap-4 px-4 lg:px-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div className="flex flex-col gap-2">
          <Button variant="outline" size="sm" className="w-fit px-0" onClick={() => navigate("/dashboard/payments")}>
            <ArrowLeftIcon />
            Payments
          </Button>
          <h1 className="text-2xl font-bold">{attributes.number}</h1>
          <p className="text-sm text-muted-foreground">
            {PAYMENT_DIRECTION_LABELS[attributes.direction]} ·
            {PAYMENT_METHOD_LABELS[attributes.method]}
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Button variant="outline" onClick={reload}>
            <RefreshCwIcon className="size-4" />
          </Button>
        </div>
      </div>

      {error && (
        <div className="flex flex-col gap-2 border border-destructive/40 bg-destructive/5 px-4 py-3 text-sm">
          <span className="text-destructive">{error}</span>
        </div>
      )}

      <div className="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <Card>
          <CardHeader>
            <CardTitle>Payment details</CardTitle>
          </CardHeader>
          <CardContent className="flex flex-col gap-4">
            <div className="grid gap-4 sm:grid-cols-2">
              <Detail label="Number" value={attributes.number} />
              <Detail label="Direction" value={PAYMENT_DIRECTION_LABELS[attributes.direction]} />
              <Detail label="Method" value={PAYMENT_METHOD_LABELS[attributes.method]} />
              <Detail label="Status" value={PAYMENT_STATUS_LABELS[attributes.status]} />
              <Detail label="Party ID" value={attributes.partyId.slice(0, 8)} />
              <Detail label="Party" value={attributes.partyName} />
              <Detail label="Currency" value={attributes.currency} />
              <Detail
                label="Paid at"
                value={attributes.paidAt ?? "—"}
              />
              <Detail
                label="Reference"
                value={attributes.reference ?? "—"}
              />
              <Detail
                label="Version"
                value={String(attributes.version)}
              />
            </div>

            <div className="pt-4 border-t">
              <div className="flex justify-between border-b py-2 font-bold">
                <span className="text-sm text-muted-foreground">Amount</span>
                <span className="font-mono tabular-nums text-right">
                  {formatMoney(attributes.amount, attributes.currency)}
                </span>
              </div>
            </div>
          </CardContent>
          <CardFooter className="flex justify-between items-center">
            <Badge variant={PAYMENT_STATUS_VARIANTS[attributes.status]}>
              {PAYMENT_STATUS_LABELS[attributes.status]}
            </Badge>
          </CardFooter>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Actions</CardTitle>
          </CardHeader>
          <CardContent className="flex flex-col gap-2">
            {attributes.status === "PENDING" && (
              <>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => handleAction("record")}
                >
                  Record (cheque cleared)
                </Button>
                <Button
                  variant="destructive"
                  size="sm"
                  onClick={() => handleAction("fail")}
                >
                  Mark as failed
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => navigate("/dashboard/payments")}
                >
                  Cancel
                </Button>
              </>
            )}
            {attributes.status === "COMPLETED" && (
              <Button
                variant="destructive"
                size="sm"
                onClick={() => handleAction("reverse")}
              >
                Reverse payment
              </Button>
            )}
            {attributes.status === "PENDING" && (
              <Button
                variant="destructive"
                size="sm"
                onClick={() => handleAction("cancel")}
              >
                Cancel payment
              </Button>
            )}
            {attributes.status !== "PENDING" &&
              attributes.status !== "COMPLETED" && (
                <p className="text-xs text-muted-foreground">
                  No further actions available for this status.
                </p>
              )}
          </CardContent>
        </Card>

        {attributes.allocations.length > 0 && (
          <Card>
            <CardHeader>
              <CardTitle>Allocations</CardTitle>
              <CardDescription>
                {attributes.allocations.length} allocation
                {attributes.allocations.length === 1 ? "" : "s"}
              </CardDescription>
            </CardHeader>
            <CardContent>
              <div className="overflow-hidden border">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Installment</TableHead>
                      <TableHead>Invoice</TableHead>
                      <TableHead className="text-right">Amount</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {attributes.allocations.map((allocation) => (
                      <TableRow key={allocation.id}>
                        <TableCell className="font-mono text-xs">
                          {allocation.attributes.installmentId.slice(0, 8)}…
                        </TableCell>
                        <TableCell className="font-mono text-xs">
                          {allocation.attributes.invoiceId.slice(0, 8)}…
                        </TableCell>
                        <TableCell className="text-right font-mono tabular-nums">
                          {formatMoney(allocation.attributes.amount, attributes.currency)}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
            </CardContent>
          </Card>
        )}
      </div>
    </div>
  )
}
