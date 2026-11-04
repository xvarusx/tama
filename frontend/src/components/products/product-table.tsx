import { MoreHorizontalIcon, PackageOpenIcon } from "lucide-react"

import type { Product, ProductVariant } from "@/services/products-api"
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
import { Skeleton } from "@/components/ui/skeleton"
import {
  Empty,
  EmptyDescription,
  EmptyHeader,
  EmptyMedia,
  EmptyTitle,
} from "@/components/ui/empty"

export type ProductsStatus = "loading" | "error" | "ready"

const COLUMN_COUNT = 7

interface ProductsTableProps {
  products: Product[]
  status: ProductsStatus
  error: string
  onRetry: () => void
  onEdit: (product: Product) => void
  onDelete: (product: Product) => void
}

function priceRange(variants: ProductVariant[]): string {
  const prices = variants
    .map((variant) => Number(variant.sellingPrice))
    .filter((price) => Number.isFinite(price))

  if (prices.length === 0) return "—"

  const min = Math.min(...prices)
  const max = Math.max(...prices)

  return min === max ? min.toFixed(2) : `${min.toFixed(2)} – ${max.toFixed(2)}`
}

function SkuSummary({ variants }: { variants: ProductVariant[] }) {
  if (variants.length === 0) return <span className="text-muted-foreground">—</span>

  return (
    <div className="flex items-center gap-2">
      <span className="font-mono text-xs">{variants[0].sku}</span>
      {variants.length > 1 && (
        <span className="text-xs text-muted-foreground">
          +{variants.length - 1}
        </span>
      )}
    </div>
  )
}

export default function ProductsTable({
  products,
  status,
  error,
  onRetry,
  onEdit,
  onDelete,
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
            <TableHead>SKUs</TableHead>
            <TableHead className="text-right">Selling price</TableHead>
            <TableHead className="text-right">Actions</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {status === "loading" && (
            <>
              {Array.from({ length: 5 }).map((_, index) => (
                <TableRow key={index}>
                  {Array.from({ length: COLUMN_COUNT }).map((__, cellIndex) => (
                    <TableCell key={cellIndex} className="p-4">
                      <Skeleton className="h-4 w-3/4" />
                    </TableCell>
                  ))}
                </TableRow>
              ))}
            </>
          )}

          {status === "error" && (
            <TableRow>
              <TableCell colSpan={COLUMN_COUNT} className="p-6 text-center">
                <div className="flex flex-col items-center gap-3">
                  <span className="text-destructive">{error}</span>
                  <Button variant="outline" size="sm" onClick={onRetry}>
                    Retry
                  </Button>
                </div>
              </TableCell>
            </TableRow>
          )}

          {status === "ready" && products.length === 0 && (
            <TableRow>
              <TableCell colSpan={COLUMN_COUNT} className="p-0">
                <Empty>
                  <EmptyHeader>
                    <EmptyMedia variant="icon">
                      <PackageOpenIcon />
                    </EmptyMedia>
                    <EmptyTitle>No products yet</EmptyTitle>
                    <EmptyDescription>
                      Create a product with one or more variants to start your
                      catalog.
                    </EmptyDescription>
                  </EmptyHeader>
                </Empty>
              </TableCell>
            </TableRow>
          )}

          {status === "ready" &&
            products.map((product) => {
              const { attributes } = product
              const variantCount = attributes.variants.length

              return (
                <TableRow key={product.id}>
                  <TableCell>
                    <div className="flex flex-col">
                      <span className="font-medium">{attributes.name}</span>
                      <span className="text-xs text-muted-foreground">
                        {variantCount} variant{variantCount === 1 ? "" : "s"}
                      </span>
                    </div>
                  </TableCell>
                  <TableCell>{attributes.brand.name}</TableCell>
                  <TableCell>{attributes.category?.name ?? "—"}</TableCell>
                  <TableCell>{attributes.unit.code}</TableCell>
                  <TableCell>
                    <SkuSummary variants={attributes.variants} />
                  </TableCell>
                  <TableCell className="text-right">
                    <span className="font-mono tabular-nums">
                      {priceRange(attributes.variants)}
                    </span>
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
                          variant="destructive"
                          onClick={() => onDelete(product)}
                        >
                          Delete
                        </DropdownMenuItem>
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </TableCell>
                </TableRow>
              )
            })}
        </TableBody>
      </Table>
    </div>
  )
}
