import { useEffect, useState } from "react"
import { ArrowLeftIcon, PencilIcon, Trash2Icon } from "lucide-react"
import { useNavigate, useParams } from "react-router-dom"

import {
  getBrands,
  getCategories,
  getProduct,
  getUnits,
  productMediaUrl,
  type Product,
  type ProductBrand,
  type ProductCategory,
  type ProductUnit,
} from "@/services/products-api"
import { getApiErrorMessage } from "@/lib/api"
import { DeleteProductDialog } from "@/components/products/delete-product-dialog"
import { ProductFormSheet } from "@/components/products/product-form-sheet"
import { Button } from "@/components/ui/button"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import { Skeleton } from "@/components/ui/skeleton"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"

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

export default function ProductShowPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const productId = Number(id)

  const [product, setProduct] = useState<Product | null>(null)
  const [status, setStatus] = useState<Status>("loading")
  const [error, setError] = useState("")
  const [reloadKey, setReloadKey] = useState(0)

  const [brands, setBrands] = useState<ProductBrand[]>([])
  const [units, setUnits] = useState<ProductUnit[]>([])
  const [categories, setCategories] = useState<ProductCategory[]>([])
  const [referencesReady, setReferencesReady] = useState(false)

  const [editOpen, setEditOpen] = useState(false)
  const [deleteOpen, setDeleteOpen] = useState(false)

  useEffect(() => {
    let cancelled = false

    const request =
      Number.isInteger(productId) && productId > 0
        ? Promise.all([
          getProduct(productId),
          getBrands(),
          getUnits(),
          getCategories(),
        ])
        : Promise.reject(new Error("Invalid product id."))

    request
      .then(([nextProduct, nextBrands, nextUnits, nextCategories]) => {
        if (cancelled) return

        setProduct(nextProduct)
        setBrands(nextBrands)
        setUnits(nextUnits)
        setCategories(nextCategories)
        setReferencesReady(true)
        setStatus("ready")
      })
      .catch((cause) => {
        if (cancelled) return

        setError(getApiErrorMessage(cause, "Unable to load the product."))
        setStatus("error")
      })

    return () => {
      cancelled = true
    }
  }, [productId, reloadKey])

  function reload() {
    setStatus("loading")
    setError("")
    setReloadKey((key) => key + 1)
  }

  if (status === "loading") {
    return (
      <div className="flex flex-1 flex-col gap-4 px-4 lg:px-6">
        <Skeleton className="h-8 w-64" />
        <div className="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
          <Skeleton className="h-80" />
          <Skeleton className="h-80" />
        </div>
        <Skeleton className="h-48" />
      </div>
    )
  }

  if (status === "error" || !product) {
    return (
      <div className="flex flex-1 flex-col items-start gap-4 px-4 lg:px-6">
        <Button variant="outline" onClick={() => navigate("/dashboard/products")}>
          <ArrowLeftIcon />
          Back to products
        </Button>
        <p className="text-destructive">{error}</p>
      </div>
    )
  }

  const { attributes } = product

  return (
    <div className="flex flex-1 flex-col gap-4 px-4 lg:px-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div className="flex flex-col gap-2">
          <Button variant="outline"
            size="sm"
            className="w-fit px-0"
            onClick={() => navigate("/dashboard/products")}
          >
            <ArrowLeftIcon />
            Products
          </Button>
          <h1 className="text-2xl font-bold">{attributes.name}</h1>
          <p className="text-sm text-muted-foreground">
            {attributes.brand.name}
            {attributes.category ? ` · ${attributes.category.name}` : ""}
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Button
            variant="outline"
            onClick={() => setEditOpen(true)}
            disabled={!referencesReady}
          >
            <PencilIcon />
            Edit
          </Button>
          <Button variant="destructive" onClick={() => setDeleteOpen(true)}>
            <Trash2Icon />
            Delete
          </Button>
        </div>
      </div>

      <div className="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <Card>
          <CardHeader>
            <CardTitle>Images</CardTitle>
            <CardDescription>
              {attributes.media.length} image
              {attributes.media.length === 1 ? "" : "s"}
            </CardDescription>
          </CardHeader>
          <CardContent>
            {attributes.media.length === 0 ? (
              <p className="text-sm text-muted-foreground">No images yet.</p>
            ) : (
              <div className="grid gap-3 sm:grid-cols-2">
                {attributes.media.map((media) => (
                  <a
                    key={media.id}
                    href={productMediaUrl(media)}
                    target="_blank"
                    rel="noreferrer"
                    className="block overflow-hidden border bg-muted"
                  >
                    <img
                      src={productMediaUrl(media)}
                      alt={`${attributes.name} image`}
                      className="aspect-square w-full object-cover"
                    />
                  </a>
                ))}
              </div>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Details</CardTitle>
          </CardHeader>
          <CardContent className="flex flex-col">
            <Detail label="Brand" value={attributes.brand.name} />
            <Detail label="Unit" value={attributes.unit.code} />
            <Detail
              label="Category"
              value={attributes.category?.name ?? "No category"}
            />
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
            <div className="flex flex-col gap-1 py-2">
              <span className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                Description
              </span>
              <span className="text-sm whitespace-pre-wrap">
                {attributes.description || "No description."}
              </span>
            </div>
            <Button
              variant="link"
              className="mt-2 w-fit px-0"
              onClick={() => navigate("/dashboard/products")}
            >
              Back to the catalog
            </Button>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Variants</CardTitle>
          <CardDescription>
            {attributes.variants.length} variant
            {attributes.variants.length === 1 ? "" : "s"}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="overflow-hidden border">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>SKU</TableHead>
                  <TableHead>Barcode</TableHead>
                  <TableHead className="text-right">Cost price</TableHead>
                  <TableHead className="text-right">Selling price</TableHead>
                  <TableHead>Options</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {attributes.variants.map((variant) => (
                  <TableRow key={variant.id}>
                    <TableCell className="font-mono text-xs">
                      {variant.sku}
                    </TableCell>
                    <TableCell>{variant.barcode ?? "—"}</TableCell>
                    <TableCell className="text-right font-mono tabular-nums">
                      {variant.costPrice ?? "—"}
                    </TableCell>
                    <TableCell className="text-right font-mono tabular-nums">
                      {variant.sellingPrice}
                    </TableCell>
                    <TableCell>
                      {variant.options
                        ? Object.entries(variant.options)
                          .map(([key, value]) => `${key}: ${value}`)
                          .join(" · ")
                        : "—"}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
        </CardContent>
      </Card>

      {editOpen && (
        <ProductFormSheet
          open
          product={product}
          brands={brands}
          units={units}
          categories={categories}
          onOpenChange={(open) => {
            if (!open) setEditOpen(false)
          }}
          onSaved={() => {
            setEditOpen(false)
            reload()
          }}
          onReferenceCreated={(kind, item) => {
            if (kind === "brand") {
              setBrands((current) =>
                [...current, item as ProductBrand].sort((a, b) =>
                  a.name.localeCompare(b.name),
                ),
              )
            } else {
              setCategories((current) =>
                [...current, item as ProductCategory].sort((a, b) =>
                  a.name.localeCompare(b.name),
                ),
              )
            }
          }}
        />
      )}

      {deleteOpen && (
        <DeleteProductDialog
          product={product}
          onOpenChange={(open) => {
            if (!open) setDeleteOpen(false)
          }}
          onDeleted={() => navigate("/dashboard/products")}
        />
      )}
    </div>
  )
}
