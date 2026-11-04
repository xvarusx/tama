import { useEffect, useState } from "react"
import { CommandIcon, PlusIcon, SearchIcon } from "lucide-react"

import getProducts, {
  getBrands,
  getCategories,
  getUnits,
  type Product,
  type ProductBrand,
  type ProductCategory,
  type ProductPagination,
  type ProductUnit,
} from "@/services/products-api"
import { getApiErrorMessage } from "@/lib/api"
import { getPageAfterDelete } from "@/lib/pagination"
import TablePagination from "@/components/common/table-pagination"
import { ProductCommandPalette } from "@/components/products/product-command-palette"
import { DeleteProductDialog } from "@/components/products/delete-product-dialog"
import { ProductFormSheet } from "@/components/products/product-form-sheet"
import ProductsTable, {
  type ProductsStatus,
} from "@/components/products/product-table"
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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"

const ALL = "all"

export default function ProductsPage() {
  const [page, setPage] = useState(1)
  const [pageSize, setPageSize] = useState(10)
  const [searchInput, setSearchInput] = useState("")
  const [search, setSearch] = useState("")
  const [brandId, setBrandId] = useState(ALL)
  const [categoryId, setCategoryId] = useState(ALL)

  const [products, setProducts] = useState<Product[]>([])
  const [pagination, setPagination] = useState<ProductPagination | null>(null)
  const [status, setStatus] = useState<ProductsStatus>("loading")
  const [error, setError] = useState("")
  const [reloadKey, setReloadKey] = useState(0)

  const [brands, setBrands] = useState<ProductBrand[]>([])
  const [units, setUnits] = useState<ProductUnit[]>([])
  const [categories, setCategories] = useState<ProductCategory[]>([])
  const [referencesStatus, setReferencesStatus] = useState<
    "loading" | "ready" | "error"
  >("loading")
  const [referencesError, setReferencesError] = useState("")
  const [referencesKey, setReferencesKey] = useState(0)

  const [formOpen, setFormOpen] = useState(false)
  const [editingProduct, setEditingProduct] = useState<Product | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<Product | null>(null)
  const [commandPaletteOpen, setCommandPaletteOpen] = useState(false)

  useEffect(() => {
    const nextSearch = searchInput.trim()

    if (nextSearch === search) return

    const timer = window.setTimeout(() => {
      beginLoading()
      setSearch(nextSearch)
      setPage(1)
    }, 300)

    return () => window.clearTimeout(timer)
  }, [searchInput, search])

  useEffect(() => {
    let cancelled = false

    Promise.all([getBrands(), getUnits(), getCategories()])
      .then(([nextBrands, nextUnits, nextCategories]) => {
        if (cancelled) return

        setBrands(nextBrands)
        setUnits(nextUnits)
        setCategories(nextCategories)
        setReferencesStatus("ready")
      })
      .catch((cause) => {
        if (cancelled) return

        setReferencesError(
          getApiErrorMessage(cause, "Unable to load brands, units and categories."),
        )
        setReferencesStatus("error")
      })

    return () => {
      cancelled = true
    }
  }, [referencesKey])

  useEffect(() => {
    let cancelled = false

    getProducts({
      page,
      pageSize,
      search: search || undefined,
      brandId: brandId === ALL ? undefined : Number(brandId),
      categoryId: categoryId === ALL ? undefined : Number(categoryId),
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

        setProducts(result.items)
        setPagination(result.pagination)
        setStatus("ready")
      })
      .catch((cause) => {
        if (cancelled) return

        setError(getApiErrorMessage(cause, "Unable to load products."))
        setStatus("error")
      })

    return () => {
      cancelled = true
    }
  }, [page, pageSize, search, brandId, categoryId, reloadKey])

  function beginLoading() {
    setStatus("loading")
    setError("")
  }

  function refresh() {
    beginLoading()
    setReloadKey((key) => key + 1)
  }

  function openCreate() {
    setEditingProduct(null)
    setFormOpen(true)
  }

  function openEdit(product: Product) {
    setEditingProduct(product)
    setFormOpen(true)
  }

  function handleSaved() {
    setFormOpen(false)
    setEditingProduct(null)
    refresh()
  }

  function handleDeleted() {
    const nextPage = getPageAfterDelete(page, products.length)

    setDeleteTarget(null)

    if (nextPage !== page) {
      beginLoading()
      setPage(nextPage)
    } else {
      refresh()
    }
  }

  function changeFilter(setter: (value: string) => void, value: string | null) {
    beginLoading()
    setter(value ?? ALL)
    setPage(1)
  }

  function retryReferences() {
    setReferencesStatus("loading")
    setReferencesError("")
    setReferencesKey((key) => key + 1)
  }

  const brandItems = {
    [ALL]: "All brands",
    ...Object.fromEntries(brands.map((brand) => [String(brand.id), brand.name])),
  }
  const categoryItems = {
    [ALL]: "All categories",
    ...Object.fromEntries(
      categories.map((category) => [String(category.id), category.name]),
    ),
  }
  const referencesReady = referencesStatus === "ready"

  return (
    <div className="relative flex flex-1 flex-col gap-4 overflow-auto px-4 outline-none lg:px-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold">Products</h1>
          <p className="text-sm text-muted-foreground">
            Manage the catalog and the variants you sell.
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Button
            variant="outline"
            size="sm"
            onClick={() => setCommandPaletteOpen(true)}
          >
            <CommandIcon />
            <span className="hidden sm:inline">⌘K</span>
          </Button>
          <Button
            size="sm"
            onClick={openCreate}
            disabled={!referencesReady}
            title={
              referencesReady
                ? undefined
                : "Waiting for brands, units and categories."
            }
          >
            <PlusIcon />
            Add product
          </Button>
        </div>
      </div>

      {referencesStatus === "error" && (
        <div className="flex flex-col gap-2 border border-destructive/40 bg-destructive/5 px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between">
          <span className="text-destructive">{referencesError}</span>
          <Button
            variant="outline"
            size="sm"
            onClick={retryReferences}
          >
            Retry
          </Button>
        </div>
      )}

      <Card>
        <CardHeader>
          <CardTitle>Catalog</CardTitle>
          <CardDescription>
            {pagination
              ? `${pagination.totalItems} product${pagination.totalItems === 1 ? "" : "s"}`
              : "Loading products…"}
          </CardDescription>
        </CardHeader>

        <CardContent className="flex flex-col gap-4">
          <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
            <InputGroup className="sm:max-w-sm">
              <InputGroupAddon>
                <SearchIcon />
              </InputGroupAddon>
              <InputGroupInput
                placeholder="Search by name..."
                value={searchInput}
                onChange={(event) => setSearchInput(event.target.value)}
                aria-label="Search products"
              />
            </InputGroup>

            <Select
              items={brandItems}
              value={brandId}
              onValueChange={(value) => changeFilter(setBrandId, value)}
            >
              <SelectTrigger className="w-full sm:w-44" aria-label="Filter by brand">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={ALL}>All brands</SelectItem>
                {brands.map((brand) => (
                  <SelectItem key={brand.id} value={String(brand.id)}>
                    {brand.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>

            <Select
              items={categoryItems}
              value={categoryId}
              onValueChange={(value) => changeFilter(setCategoryId, value)}
            >
              <SelectTrigger
                className="w-full sm:w-44"
                aria-label="Filter by category"
              >
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={ALL}>All categories</SelectItem>
                {categories.map((category) => (
                  <SelectItem key={category.id} value={String(category.id)}>
                    {category.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <ProductsTable
            products={products}
            status={status}
            error={error}
            onRetry={refresh}
            onEdit={openEdit}
            onDelete={setDeleteTarget}
          />
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

      <ProductCommandPalette
        products={products}
        onCreate={openCreate}
        onEdit={openEdit}
        onDelete={setDeleteTarget}
        open={commandPaletteOpen}
        onOpenChange={setCommandPaletteOpen}
      />

      {formOpen && (
        <ProductFormSheet
          open
          product={editingProduct}
          brands={brands}
          units={units}
          categories={categories}
          onOpenChange={(open) => {
            if (!open) {
              setFormOpen(false)
              setEditingProduct(null)
            }
          }}
          onSaved={handleSaved}
        />
      )}

      {deleteTarget && (
        <DeleteProductDialog
          product={deleteTarget}
          onOpenChange={(open) => {
            if (!open) setDeleteTarget(null)
          }}
          onDeleted={handleDeleted}
        />
      )}
    </div>
  )
}
