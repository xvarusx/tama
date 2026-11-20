import { useEffect, useState } from "react"
import { ArrowLeftIcon, RefreshCwIcon } from "lucide-react"
import { useNavigate, useParams } from "react-router-dom"

import {
  getReminder,
  type Reminder,
} from "@/services/payments-api"
import { getApiErrorMessage } from "@/lib/api"
import { Button } from "@/components/ui/button"
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  CardFooter,
} from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Skeleton } from "@/components/ui/skeleton"

type Status = "loading" | "error" | "ready"

const KIND_LABELS: Record<string, string> = {
  UPCOMING_DUE: "Upcoming due",
  OVERDUE: "Overdue",
}

const KIND_VARIANTS: Record<string, "default" | "outline" | "secondary" | "ghost" | "destructive" | "link"> = {
  UPCOMING_DUE: "secondary",
  OVERDUE: "destructive",
}

function Detail({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="flex flex-col gap-1 border-b py-2 last:border-b-0">
      <span className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
        {label}
      </span>
      <span>{value}</span>
    </div>
  )
}

export default function ReminderShowPage() {
  const { id } = useParams()
  const navigate = useNavigate()

  const [reminder, setReminder] = useState<Reminder | null>(null)
  const [status, setStatus] = useState<Status>("loading")
  const [error, setError] = useState("")
  const [reloadKey, setReloadKey] = useState(0)

  useEffect(() => {
    let cancelled = false

    if (!id) return

    getReminder(id)
      .then((nextReminder) => {
        if (cancelled) return
        setReminder(nextReminder)
        setStatus("ready")
      })
      .catch((cause) => {
        if (cancelled) return
        setError(getApiErrorMessage(cause, "Unable to load the reminder."))
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
        <Button variant="outline" onClick={() => navigate("/dashboard/reminders")}>
          <ArrowLeftIcon />
          Back to reminders
        </Button>
        <p className="text-destructive">Invalid reminder id.</p>
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

  if (status === "error" || !reminder) {
    return (
      <div className="flex flex-1 flex-col items-start gap-4 px-4 lg:px-6">
        <Button variant="outline" onClick={() => navigate("/dashboard/reminders")}>
          <ArrowLeftIcon />
          Back to reminders
        </Button>
        <p className="text-destructive">{error}</p>
      </div>
    )
  }

  const { attributes } = reminder

  return (
    <div className="flex flex-1 flex-col gap-4 px-4 lg:px-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div className="flex flex-col gap-2">
          <Button variant="outline" size="sm" className="w-fit px-0" onClick={() => navigate("/dashboard/reminders")}>
            <ArrowLeftIcon />
            Reminders
          </Button>
          <h1 className="text-2xl font-bold">Reminder detail</h1>
          <p className="text-sm text-muted-foreground">
            Installment {attributes.installmentId.slice(0, 8)}…
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
          <CardTitle>Reminder details</CardTitle>
        </CardHeader>
        <CardContent className="flex flex-col gap-4">
          <div className="grid gap-4 sm:grid-cols-2">
            <Detail label="Installment ID" value={attributes.installmentId} />
            <Detail
              label="Kind"
              value={
                <Badge variant={KIND_VARIANTS[attributes.kind]}>
                  {KIND_LABELS[attributes.kind]}
                </Badge>
              }
            />
            <Detail label="Scheduled for" value={attributes.scheduledFor} />
            <Detail label="Status" value={attributes.status} />
            <Detail label="Sent at" value={attributes.sentAt ?? "—"} />
          </div>
        </CardContent>
        <CardFooter className="flex justify-between items-center">
          <Badge variant="secondary">{attributes.status}</Badge>
        </CardFooter>
      </Card>
    </div>
  )
}