import { MoreHorizontalIcon } from "lucide-react"

import {
  PRODUCT_STATUS,
  type Product,
  type ProductStatus,
} from "@/services/products-api"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"

export type ProductsStatus = "loading" | "error" | "ready"

const COLUMN_COUNT = 8

interface ProductsTableProps {
  products: Product[]
  status: ProductsStatus
  error: string
  onRetry: () => void
  onEdit: (product: Product) => void
  onStatusChange: (product: Product) => void
}

export default function ProductsTable({
  products,
  status,
  error,
  onRetry,
  onEdit,
  onStatusChange,
}: ProductsTableProps) {
  return (
    <div className="overflow-hidden border">
      <Table>
        <TableHeader className="sticky top-0 z-10 bg-muted">
          <TableRow>
            <TableHead>Product</TableHead>
            <TableHead>Brand</TableHead>
            <TableHead>Category</TableHead>
            <TableHead>Unit</TableHead>
            <TableHead>Selling</TableHead>
            <TableHead>Cost</TableHead>
            <TableHead>Status</TableHead>
            <TableHead className="text-right">Actions</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody className="**:data-[slot=table-cell]:first:w-8">
          {status === "loading" && (
            <TableRow>
              <TableCell
                colSpan={COLUMN_COUNT}
                className="text-center text-muted-foreground"
              >
                Loading products…
              </TableCell>
            </TableRow>
          )}

          {status === "error" && (
            <TableRow>
              <TableCell colSpan={COLUMN_COUNT} className="text-center">
                <span className="text-destructive">{error}</span>
                <Button
                  variant="outline"
                  size="sm"
                  className="ml-3"
                  onClick={onRetry}
                >
                  Retry
                </Button>
              </TableCell>
            </TableRow>
          )}

          {status === "ready" && products.length === 0 && (
            <TableRow>
              <TableCell
                colSpan={COLUMN_COUNT}
                className="text-center text-muted-foreground"
              >
                No products yet.
              </TableCell>
            </TableRow>
          )}

          {status === "ready" &&
            products.map((product) => (
              <TableRow key={product.id}>
                <TableCell>
                  <div className="flex items-center gap-3">
                    {product.imageUrl && (
                      <img
                        src={product.imageUrl}
                        alt=""
                        className="size-8 border object-cover"
                      />
                    )}
                    <div className="flex flex-col">
                      <span className="font-medium">{product.name}</span>
                      <span className="text-xs text-muted-foreground">
                        {product.sku}
                      </span>
                    </div>
                  </div>
                </TableCell>
                <TableCell>{product.brand ?? "—"}</TableCell>
                <TableCell>{product.category?.name ?? "—"}</TableCell>
                <TableCell>{product.unit}</TableCell>
                <TableCell>{product.sellingPrice}</TableCell>
                <TableCell>{product.costPrice}</TableCell>
                <TableCell>
                  <Badge
                    variant={product.status === "active" ? "default" : "secondary"}
                  >
                    {PRODUCT_STATUS[product.status as ProductStatus] ??
                      product.status}
                  </Badge>
                </TableCell>
                <TableCell className="text-right">
                  <DropdownMenu>
                    <DropdownMenuTrigger
                      render={
                        <Button variant="ghost" size="icon" className="size-8">
                          <MoreHorizontalIcon />
                          <span className="sr-only">Open menu</span>
                        </Button>
                      }
                    />
                    <DropdownMenuContent align="end">
                      <DropdownMenuItem onClick={() => onEdit(product)}>
                        Edit
                      </DropdownMenuItem>
                      <DropdownMenuSeparator />
                      <DropdownMenuItem
                        variant={
                          product.status === "archive"
                            ? "default"
                            : "destructive"
                        }
                        onClick={() => onStatusChange(product)}
                      >
                        {product.status === "archive"
                          ? "Activate product"
                          : "Archive product"}
                      </DropdownMenuItem>
                    </DropdownMenuContent>
                  </DropdownMenu>
                </TableCell>
              </TableRow>
            ))}
        </TableBody>
      </Table>
    </div>
  )
}
