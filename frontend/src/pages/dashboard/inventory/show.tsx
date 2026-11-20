import { useEffect, useState } from "react"
import { ArrowLeftIcon, RefreshCwIcon } from "lucide-react"
import { useNavigate, useParams } from "react-router-dom"

import {
  getInventory,
  getMovements,
  type InventoryItem,
  type InventoryMovement,
} from "@/services/inventory-api"
import { getApiErrorMessage } from "@/lib/api"
import { Button } from "@/components/ui/button"
import {
  Card,
  CardContent,
  CardDescription,
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

const MOVEMENT_TYPE_BADGE: Record<string, string> = {
  in: "success",
  out: "destructive",
  adjustment: "warning",
}

export default function InventoryShowPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const inventoryId = id

  const [item, setItem] = useState<InventoryItem | null>(null)
  const [movements, setMovements] = useState<InventoryMovement[]>([])
  const [status, setStatus] = useState<Status>("loading")
  const [error, setError] = useState("")
  const [reloadKey, setReloadKey] = useState(0)

  useEffect(() => {
    let cancelled = false

    const request = inventoryId
      ? Promise.all([getInventory(inventoryId), getMovements(inventoryId, 1, 50)])
      : Promise.reject(new Error("Invalid inventory id."))

    request
      .then(([nextItem, { items: nextMovements }]) => {
        if (cancelled) return
        setItem(nextItem)
        setMovements(nextMovements)
        setStatus("ready")
      })
      .catch((cause) => {
        if (cancelled) return
        setError(getApiErrorMessage(cause, "Unable to load inventory item."))
        setStatus("error")
      })

    return () => {
      cancelled = true
    }
  }, [inventoryId, reloadKey])

  function reload() {
    setStatus("loading")
    setError("")
    setReloadKey((key) => key + 1)
  }

  if (!inventoryId) {
    return (
      <div className="flex flex-1 flex-col items-start gap-4 px-4 lg:px-6">
        <Button variant="outline" onClick={() => navigate("/dashboard/inventory")}>
          <ArrowLeftIcon />
          Back to inventory
        </Button>
        <p className="text-destructive">Invalid inventory id.</p>
      </div>
    )
  }

  if (status === "loading") {
    return (
      <div className="flex flex-1 flex-col gap-4 px-4 lg:px-6">
        <Skeleton className="h-8 w-64" />
        <Skeleton className="h-96" />
        <Skeleton className="h-48" />
      </div>
    )
  }

  if (status === "error" || !item) {
    return (
      <div className="flex flex-1 flex-col items-start gap-4 px-4 lg:px-6">
        <Button variant="outline" onClick={() => navigate("/dashboard/inventory")}>
          <ArrowLeftIcon />
          Back to inventory
        </Button>
        <p className="text-destructive">{error}</p>
      </div>
    )
  }

  return (
    <div className="flex flex-1 flex-col gap-4 px-4 lg:px-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div className="flex flex-col gap-2">
          <Button variant="outline" size="sm" className="w-fit px-0" onClick={() => navigate("/dashboard/inventory")}>
            <ArrowLeftIcon />
            Inventory
          </Button>
          <h1 className="text-2xl font-bold">Product {item.productId}</h1>
          <p className="text-sm text-muted-foreground">
            Stock levels and movements
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
            <CardTitle>Stock levels</CardTitle>
          </CardHeader>
          <CardContent className="flex flex-col gap-4">
            <div className="grid gap-4 sm:grid-cols-2">
              <Detail label="Product ID" value={String(item.productId)} />
              <Detail label="Quantity on hand" value={item.quantityOnHand} />
              <Detail label="Quantity reserved" value={item.quantityReserved} />
              <Detail label="Reorder level" value={item.reorderLevel} />
              <Detail label="Available quantity" value={item.availableQuantity} />
              <Detail label="Version" value={String(item.version)} />
              <Detail
                label="Low stock"
                value={item.lowStock ? "Yes" : "No"}
              />
              <Detail
                label="Created"
                value={
                  item.createdAt
                    ? new Date(item.createdAt).toLocaleString()
                    : "—"
                }
              />
              <Detail
                label="Updated"
                value={
                  item.updatedAt
                    ? new Date(item.updatedAt).toLocaleString()
                    : "—"
                }
              />
            </div>

            <div className="pt-4 border-t">
              <Badge
                variant={item.lowStock ? "destructive" : "success"}
                className="w-fit"
              >
                {item.lowStock ? "Low stock" : "OK"}
              </Badge>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Recent movements</CardTitle>
            <CardDescription>
              {movements.length} movement
              {movements.length === 1 ? "" : "s"}
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="overflow-hidden border">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Date</TableHead>
                    <TableHead className="text-right">Type</TableHead>
                    <TableHead className="text-right">Quantity</TableHead>
                    <TableHead className="text-right">After</TableHead>
                    <TableHead>Reference</TableHead>
                    <TableHead>Reason</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {movements.length === 0 && (
                    <TableRow>
                      <TableCell colSpan={6} className="text-center text-muted-foreground">
                        No movements yet.
                      </TableCell>
                    </TableRow>
                  )}
                  {movements.map((movement) => (
                    <TableRow key={movement.id}>
                      <TableCell className="text-xs">
                        {new Date(movement.createdAt).toLocaleString()}
                      </TableCell>
                      <TableCell className="text-right">
                        <Badge variant={MOVEMENT_TYPE_BADGE[movement.type] ?? "secondary"}>
                          {movement.type}
                        </Badge>
                      </TableCell>
                      <TableCell className="text-right font-mono tabular-nums">
                        {movement.quantity}
                      </TableCell>
                      <TableCell className="text-right font-mono tabular-nums">
                        {movement.quantityAfter ?? "—"}
                      </TableCell>
                      <TableCell className="text-xs">
                        {movement.referenceType ?? "—"}
                      </TableCell>
                      <TableCell className="text-xs">
                        {movement.reason ?? "—"}
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
