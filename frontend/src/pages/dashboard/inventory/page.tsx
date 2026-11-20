import { useEffect, useState } from "react"
import { RefreshCwIcon, SearchIcon } from "lucide-react"

import getInventories, {
  type InventoryItem,
  type InventoryPagination,
} from "@/services/inventory-api"
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
import { Badge } from "@/components/ui/badge"

const COLUMN_COUNT = 7

export default function InventoryPage() {
  const [page, setPage] = useState(1)
  const [pageSize, setPageSize] = useState(10)
  const [searchInput, setSearchInput] = useState("")
  const [search, setSearch] = useState("")

  const [items, setItems] = useState<InventoryItem[]>([])
  const [pagination, setPagination] = useState<InventoryPagination | null>(null)
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

    getInventories({
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

        setError(getApiErrorMessage(cause, "Unable to load inventory."))
        setStatus("error")
      })

    return () => {
      cancelled = true
    }
  }, [page, pageSize, search, reloadKey])

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
          <h1 className="text-2xl font-bold">Inventory</h1>
          <p className="text-sm text-muted-foreground">
            Track stock levels and movements across products.
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
          <CardTitle>Stock levels</CardTitle>
          <CardDescription>
            {pagination
              ? `${pagination.totalItems} item${
                  pagination.totalItems === 1 ? "" : "s"
                }`
              : "Loading inventory…"}
          </CardDescription>
        </CardHeader>

        <CardContent className="flex flex-col gap-4">
          <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
            <InputGroup className="sm:max-w-sm">
              <InputGroupAddon>
                <SearchIcon />
              </InputGroupAddon>
              <InputGroupInput
                placeholder="Search by product..."
                value={searchInput}
                onChange={(event) => setSearchInput(event.target.value)}
                aria-label="Search inventory"
              />
            </InputGroup>
          </div>

          <table className="w-full text-sm">
            <thead>
              <tr>
                <th className="text-left font-medium">Product ID</th>
                <th className="text-right font-medium">On hand</th>
                <th className="text-right font-medium">Reserved</th>
                <th className="text-right font-medium">Reorder level</th>
                <th className="text-right font-medium">Available</th>
                <th className="text-center font-medium">Low stock</th>
                <th className="text-right font-medium">Version</th>
              </tr>
            </thead>
            <tbody>
              {status === "loading" && (
                <tr>
                  <td colSpan={COLUMN_COUNT} className="text-center text-muted-foreground">
                    Loading inventory…
                  </td>
                </tr>
              )}
              {status === "error" && (
                <tr>
                  <td colSpan={COLUMN_COUNT} className="text-center">
                    <span className="text-destructive">{error}</span>{" "}
                    <Button variant="outline" size="sm" onClick={refresh}>
                      Retry
                    </Button>
                  </td>
                </tr>
              )}
              {status === "ready" && items.length === 0 && (
                <tr>
                  <td colSpan={COLUMN_COUNT} className="text-center text-muted-foreground">
                    No inventory items yet.
                  </td>
                </tr>
              )}
              {status === "ready" &&
                items.map((item) => (
                  <tr key={item.id}>
                    <td className="font-mono">{item.productId}</td>
                    <td className="text-right font-mono tabular-nums">
                      {item.quantityOnHand}
                    </td>
                    <td className="text-right font-mono tabular-nums">
                      {item.quantityReserved}
                    </td>
                    <td className="text-right font-mono tabular-nums">
                      {item.reorderLevel}
                    </td>
                    <td className="text-right font-mono tabular-nums">
                      {item.availableQuantity}
                    </td>
                    <td className="text-center">
                      <Badge
                        variant={item.lowStock ? "destructive" : "secondary"}
                        className="w-fit"
                      >
                        {item.lowStock ? "Yes" : "No"}
                      </Badge>
                    </td>
                    <td className="text-right font-mono">
                      {item.version}
                    </td>
                  </tr>
                ))}
            </tbody>
          </table>
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
