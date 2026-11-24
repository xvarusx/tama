import { useEffect, useState } from "react"
import { ArrowLeftIcon, RefreshCwIcon } from "lucide-react"
import { useNavigate, useParams } from "react-router-dom"

import {
  getPaymentPlan,
  cancelPaymentPlan,
  formatMoney,
  type PaymentPlan,
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

export default function PaymentPlanShowPage() {
  const { id } = useParams()
  const navigate = useNavigate()

  const [plan, setPlan] = useState<PaymentPlan | null>(null)
  const [status, setStatus] = useState<Status>("loading")
  const [error, setError] = useState("")
  const [reloadKey, setReloadKey] = useState(0)

  useEffect(() => {
    let cancelled = false

    getPaymentPlan(id)
      .then((nextPlan) => {
        if (cancelled) return
        setPlan(nextPlan)
        setStatus("ready")
      })
      .catch((cause) => {
        if (cancelled) return
        setError(getApiErrorMessage(cause, "Unable to load the payment plan."))
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

  async function handleCancel() {
    if (!plan) return

    if (
      !window.confirm(
        `Cancel payment plan ${plan.attributes.invoiceId}? This cannot be undone.`,
      )
    ) {
      return
    }

    try {
      const updated = await cancelPaymentPlan(plan.id)
      setPlan(updated)
      setStatus("ready")
    } catch (cause) {
      setError(getApiErrorMessage(cause, "Unable to cancel payment plan."))
    }
  }

  if (!id) {
    return (
      <div className="flex flex-1 flex-col items-start gap-4 px-4 lg:px-6">
        <Button variant="outline" onClick={() => navigate("/dashboard/payment-plans")}>
          <ArrowLeftIcon />
          Back to payment plans
        </Button>
        <p className="text-destructive">Invalid payment plan id.</p>
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

  if (status === "error" || !plan) {
    return (
      <div className="flex flex-1 flex-col items-start gap-4 px-4 lg:px-6">
        <Button variant="outline" onClick={() => navigate("/dashboard/payment-plans")}>
          <ArrowLeftIcon />
          Back to payment plans
        </Button>
        <p className="text-destructive">{error}</p>
      </div>
    )
  }

  const { attributes } = plan

  return (
    <div className="flex flex-1 flex-col gap-4 px-4 lg:px-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div className="flex flex-col gap-2">
          <Button variant="outline" size="sm" className="w-fit px-0" onClick={() => navigate("/dashboard/payment-plans")}>
            <ArrowLeftIcon />
            Payment plans
          </Button>
          <h1 className="text-2xl font-bold">Payment plan</h1>
          <p className="text-sm text-muted-foreground">
            Invoice {attributes.invoiceId.slice(0, 8)}…
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Button variant="outline" onClick={reload}>
            <RefreshCwIcon className="size-4" />
          </Button>
          {attributes.status !== "CANCELED" && (
            <Button variant="destructive" onClick={handleCancel}>
              Cancel plan
            </Button>
          )}
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
            <CardTitle>Plan details</CardTitle>
          </CardHeader>
          <CardContent className="flex flex-col gap-4">
            <div className="grid gap-4 sm:grid-cols-2">
              <Detail label="Invoice ID" value={attributes.invoiceId} />
              <Detail label="Status" value={attributes.status} />
              <Detail label="Currency" value={attributes.currency} />
              <Detail label="Version" value={String(attributes.version)} />
            </div>

            <div className="pt-4 border-t">
              <div className="flex justify-between border-b py-2 font-bold">
                <span className="text-sm text-muted-foreground">Total amount</span>
                <span className="font-mono tabular-nums text-right">
                  {formatMoney(attributes.totalAmount, attributes.currency)}
                </span>
              </div>
            </div>
          </CardContent>
          <CardFooter className="flex justify-between items-center">
            <Badge variant="secondary">{attributes.status}</Badge>
          </CardFooter>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Installments</CardTitle>
            <CardDescription>
              {attributes.installments.length} installment
              {attributes.installments.length === 1 ? "" : "s"}
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="overflow-hidden border">
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
                  {attributes.installments.length === 0 && (
                    <TableRow>
                      <td colSpan={6} className="text-center text-muted-foreground">
                        No installments yet.
                      </td>
                    </TableRow>
                  )}
                  {attributes.installments.map((installment) => (
                    <TableRow key={installment.id}>
                      <TableCell className="font-mono text-xs">
                        #{installment.sequence}
                      </TableCell>
                      <TableCell>{installment.dueDate}</TableCell>
                      <TableCell className="text-right font-mono tabular-nums">
                        {formatMoney(installment.amount, attributes.currency)}
                      </TableCell>
                      <TableCell className="text-right font-mono tabular-nums">
                        {formatMoney(installment.paidAmount, attributes.currency)}
                      </TableCell>
                      <TableCell className="text-right font-mono tabular-nums">
                        {formatMoney(installment.remaining, attributes.currency)}
                      </TableCell>
                      <TableCell>
                        <Badge variant="secondary">{installment.status}</Badge>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
