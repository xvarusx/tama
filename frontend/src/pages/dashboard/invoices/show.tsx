import { useEffect, useState } from "react"
import { ArrowLeftIcon, DownloadIcon } from "lucide-react"
import { useNavigate, useParams } from "react-router-dom"

import {
  getInvoice,
  getInvoicePdf,
  formatMoney,
  type Invoice,
  type InvoiceStatus,
} from "@/services/invoices-api"
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

function MoneyRow({
  label,
  value,
  currency,
}: {
  label: string
  value: number
  currency: string
}) {
  return (
    <div className="flex justify-between border-b py-2">
      <span className="text-sm text-muted-foreground">{label}</span>
      <span className="font-mono tabular-nums text-right">
        {formatMoney(value, currency)}
      </span>
    </div>
  )
}

export default function InvoiceShowPage() {
  const { id } = useParams()
  const navigate = useNavigate()

  const [invoice, setInvoice] = useState<Invoice | null>(null)
  const [status, setStatus] = useState<Status>("loading")
  const [error, setError] = useState("")

  useEffect(() => {
    let cancelled = false

    getInvoice(id)
      .then((nextInvoice) => {
        if (cancelled) return
        setInvoice(nextInvoice)
        setStatus("ready")
      })
      .catch((cause) => {
        if (cancelled) return
        setError(getApiErrorMessage(cause, "Unable to load the invoice."))
        setStatus("error")
      })

    return () => {
      cancelled = true
    }
  }, [id])

  async function downloadPdf() {
    if (!invoice) return

    try {
      const blob = await getInvoicePdf(invoice.id)
      const url = URL.createObjectURL(blob)
      const a = document.createElement("a")
      a.href = url
      a.download = `${invoice.attributes.number ?? invoice.id}.pdf`
      document.body.appendChild(a)
      a.click()
      document.body.removeChild(a)
      URL.revokeObjectURL(url)
    } catch (cause) {
      setError(getApiErrorMessage(cause, "Unable to download PDF."))
    }
  }

  if (!id) {
    return (
      <div className="flex flex-1 flex-col items-start gap-4 px-4 lg:px-6">
        <Button variant="outline" onClick={() => navigate("/dashboard/invoices")}>
          <ArrowLeftIcon />
          Back to invoices
        </Button>
        <p className="text-destructive">Invalid invoice id.</p>
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

  if (status === "error" || !invoice) {
    return (
      <div className="flex flex-1 flex-col items-start gap-4 px-4 lg:px-6">
        <Button variant="outline" onClick={() => navigate("/dashboard/invoices")}>
          <ArrowLeftIcon />
          Back to invoices
        </Button>
        <p className="text-destructive">{error}</p>
      </div>
    )
  }

  const { attributes } = invoice

  return (
    <div className="flex flex-1 flex-col gap-4 px-4 lg:px-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div className="flex flex-col gap-2">
          <Button variant="outline" size="sm" className="w-fit px-0" onClick={() => navigate("/dashboard/invoices")}>
            <ArrowLeftIcon />
            Invoices
          </Button>
          <h1 className="text-2xl font-bold">
            {attributes.number ?? "Invoice"}
          </h1>
          <p className="text-sm text-muted-foreground">
            {attributes.type} · {STATUS_LABELS[attributes.status]}
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Button
            variant="outline"
            onClick={downloadPdf}
            disabled={attributes.status === "draft"}
          >
            <DownloadIcon className="size-4" />
            PDF
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
            <CardTitle>Invoice details</CardTitle>
          </CardHeader>
          <CardContent className="flex flex-col gap-4">
            <div className="grid gap-4 sm:grid-cols-2">
              <Detail label="Number" value={attributes.number ?? "—"} />
              <Detail label="Status" value={STATUS_LABELS[attributes.status]} />
              <Detail label="Type" value={attributes.type} />
              <Detail label="Order ID" value={attributes.orderId.slice(0, 8)} />
              <Detail label="Party ID" value={attributes.partyId.slice(0, 8)} />
              <Detail label="Party" value={attributes.partyName} />
              <Detail label="Currency" value={attributes.currency} />
              <Detail label="Issue date" value={attributes.issueDate ?? "—"} />
              <Detail label="Due date" value={attributes.dueDate ?? "—"} />
              <Detail
                label="Created"
                value={
                  attributes.createdAt
                    ? new Date(attributes.createdAt).toLocaleString()
                    : "—"
                }
              />
              <Detail
                label="Updated"
                value={
                  attributes.updatedAt
                    ? new Date(attributes.updatedAt).toLocaleString()
                    : "—"
                }
              />
            </div>

            <div className="pt-4 border-t">
              <MoneyRow
                label="Total"
                value={attributes.total}
                currency={attributes.currency}
              />
              <MoneyRow
                label="Amount paid"
                value={attributes.amountPaid}
                currency={attributes.currency}
              />
              <MoneyRow
                label="Credited"
                value={attributes.creditedAmount}
                currency={attributes.currency}
              />
              <MoneyRow
                label="Balance"
                value={attributes.balance}
                currency={attributes.currency}
                className="font-bold"
              />
            </div>
          </CardContent>
          <CardFooter className="flex justify-between items-center">
            <Badge variant={STATUS_VARIANTS[attributes.status]}>
              {STATUS_LABELS[attributes.status]}
            </Badge>
            {attributes.overdue && <Badge variant="destructive">Overdue</Badge>}
            {attributes.overpaymentFlagged && (
              <Badge variant="warning">Overpayment flagged</Badge>
            )}
          </CardFooter>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Invoice lines</CardTitle>
            <CardDescription>
              {attributes.lines.length} line
              {attributes.lines.length === 1 ? "" : "s"}
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="overflow-hidden border">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Product</TableHead>
                    <TableHead>Description</TableHead>
                    <TableHead className="text-right">Qty</TableHead>
                    <TableHead className="text-right">Unit price</TableHead>
                    <TableHead className="text-right">Tax (%)</TableHead>
                    <TableHead className="text-right">Total</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {attributes.lines.map((line) => (
                    <TableRow key={line.position}>
                      <TableCell className="font-mono text-xs">
                        {line.productId.slice(0, 8)}…
                      </TableCell>
                      <TableCell>{line.description}</TableCell>
                      <TableCell className="text-right font-mono tabular-nums">
                        {line.quantity}
                      </TableCell>
                      <TableCell className="text-right font-mono tabular-nums">
                        {formatMoney(line.unitPrice, attributes.currency)}
                      </TableCell>
                      <TableCell className="text-right font-mono tabular-nums">
                        {(line.taxRateBps / 100).toFixed(2)}
                      </TableCell>
                      <TableCell className="text-right font-mono tabular-nums">
                        {formatMoney(line.total, attributes.currency)}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          </CardContent>
        </Card>

        {attributes.creditNotes.length > 0 && (
          <Card>
            <CardHeader>
              <CardTitle>Credit notes</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="overflow-hidden border">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Number</TableHead>
                      <TableHead className="text-right">Amount</TableHead>
                      <TableHead>Reason</TableHead>
                      <TableHead>Issued at</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {attributes.creditNotes.map((note) => (
                      <TableRow key={note.number}>
                        <TableCell>{note.number ?? "—"}</TableCell>
                        <TableCell className="text-right font-mono tabular-nums">
                          {formatMoney(note.amount, attributes.currency)}
                        </TableCell>
                        <TableCell>{note.reason ?? "—"}</TableCell>
                        <TableCell className="text-right text-xs">
                          {note.issuedAt}
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
