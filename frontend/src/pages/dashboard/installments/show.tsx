import { useEffect, useState } from "react"
import { ArrowLeftIcon, RefreshCwIcon } from "lucide-react"
import { useNavigate, useParams } from "react-router-dom"

import {
  getInstallment,
  formatMoney,
  type PaymentPlanInstallment,
} from "@/services/payments-api"
import { getApiErrorMessage } from "@/lib/api"
import { Button } from "@/components/ui/button"
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
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

export default function InstallmentShowPage() {
  const { id } = useParams()
  const navigate = useNavigate()

  const [installment, setInstallment] = useState<PaymentPlanInstallment | null>(null)
  const [status, setStatus] = useState<Status>("loading")
  const [error, setError] = useState("")
  const [reloadKey, setReloadKey] = useState(0)

  useEffect(() => {
    let cancelled = false

    getInstallment(id)
      .then((nextInstallment) => {
        if (cancelled) return
        setInstallment(nextInstallment)
        setStatus("ready")
      })
      .catch((cause) => {
        if (cancelled) return
        setError(getApiErrorMessage(cause, "Unable to load the installment."))
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

  if (!id) {
    return (
      <div className="flex flex-1 flex-col items-start gap-4 px-4 lg:px-6">
        <Button variant="outline" onClick={() => navigate("/dashboard/installments")}>
          <ArrowLeftIcon />
          Back to installments
        </Button>
        <p className="text-destructive">Invalid installment id.</p>
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

  if (status === "error" || !installment) {
    return (
      <div className="flex flex-1 flex-col items-start gap-4 px-4 lg:px-6">
        <Button variant="outline" onClick={() => navigate("/dashboard/installments")}>
          <ArrowLeftIcon />
          Back to installments
        </Button>
        <p className="text-destructive">{error}</p>
      </div>
    )
  }

  const { attributes } = installment

  return (
    <div className="flex flex-1 flex-col gap-4 px-4 lg:px-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div className="flex flex-col gap-2">
          <Button variant="outline" size="sm" className="w-fit px-0" onClick={() => navigate("/dashboard/installments")}>
            <ArrowLeftIcon />
            Installments
          </Button>
          <h1 className="text-2xl font-bold">Installment #{attributes.sequence}</h1>
          <p className="text-sm text-muted-foreground">
            Due {attributes.dueDate}
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

      <Card>
        <CardHeader>
          <CardTitle>Installment details</CardTitle>
        </CardHeader>
        <CardContent className="flex flex-col gap-4">
          <div className="grid gap-4 sm:grid-cols-2">
            <Detail label="Sequence" value={`#${attributes.sequence}`} />
            <Detail label="Due date" value={attributes.dueDate} />
            <Detail label="Status" value={attributes.status} />
          </div>

          <div className="pt-4 border-t">
            <div className="flex justify-between border-b py-2">
              <span className="text-sm text-muted-foreground">Amount</span>
              <span className="font-mono tabular-nums text-right">
                {formatMoney(attributes.amount, "USD")}
              </span>
            </div>
            <div className="flex justify-between border-b py-2">
              <span className="text-sm text-muted-foreground">Paid amount</span>
              <span className="font-mono tabular-nums text-right">
                {formatMoney(attributes.paidAmount, "USD")}
              </span>
            </div>
            <div className="flex justify-between border-b py-2 font-bold">
              <span className="text-sm text-muted-foreground">Remaining</span>
              <span className="font-mono tabular-nums text-right">
                {formatMoney(attributes.remaining, "USD")}
              </span>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
