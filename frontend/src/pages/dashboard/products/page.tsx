import { useEffect, useState } from "react"

import getProducts, {
  getProductsTotal,
  type Product,
} from "@/services/products-api"
import { getApiErrorMessage } from "@/lib/api"
import { clampPage } from "@/lib/pagination"
import TablePagination from "@/components/common/table-pagination"
import ProductsTable, {
  type ProductsStatus,
} from "@/components/products/product-table"
import ProductCreate from "@/components/products/product-create"
import { ProductFormDialog } from "@/components/products/product-form-dialog"
import { ProductStatusDialog } from "@/components/products/product-status-dialog"

export default function ProductsPage() {
  const [page, setPage] = useState(1)
  const [limit, setLimit] = useState(10)
  const [products, setProducts] = useState<Product[]>([])
  const [total, setTotal] = useState(0)
  const [status, setStatus] = useState<ProductsStatus>("loading")
  const [error, setError] = useState("")
  const [reloadKey, setReloadKey] = useState(0)
  const [editingProduct, setEditingProduct] = useState<Product | null>(null)
  const [statusTarget, setStatusTarget] = useState<Product | null>(null)

  useEffect(() => {
    let cancelled = false

    Promise.all([getProducts(page, limit), getProductsTotal()])
      .then(([nextProducts, nextTotal]) => {
        if (cancelled) return

        const totalPages = Math.max(1, Math.ceil(nextTotal / limit))

        if (page > totalPages) {
          setPage(clampPage(page, totalPages))
          return
        }

        setProducts(nextProducts)
        setTotal(nextTotal)
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
  }, [page, limit, reloadKey])

  function beginLoading() {
    setStatus("loading")
    setError("")
  }

  function refresh() {
    beginLoading()
    setReloadKey((key) => key + 1)
  }

  function changePage(nextPage: number) {
    if (nextPage === page) return

    beginLoading()
    setPage(nextPage)
  }

  function changeLimit(nextLimit: number) {
    if (nextLimit === limit) return

    beginLoading()
    setLimit(nextLimit)
    setPage(1)
  }

  function handleCreated() {
    beginLoading()
    setPage(1)
    setReloadKey((key) => key + 1)
  }

  function handleSaved() {
    refresh()
  }

  function handleStatusChanged() {
    setStatusTarget(null)
    refresh()
  }

  function handleRetry() {
    refresh()
  }

  return (
    <div className="flex-1 outline-none relative flex flex-col gap-4 overflow-auto px-4 lg:px-6">
      <ProductCreate onCreated={handleCreated} />
      <ProductsTable
        products={products}
        status={status}
        error={error}
        onRetry={handleRetry}
        onEdit={setEditingProduct}
        onStatusChange={setStatusTarget}
      />
      <TablePagination
        page={page}
        limit={limit}
        total={total}
        onPageChange={changePage}
        onLimitChange={changeLimit}
      />
      {editingProduct && (
        <ProductFormDialog
          open
          product={editingProduct}
          onOpenChange={(open) => {
            if (!open) setEditingProduct(null)
          }}
          onSaved={() => {
            setEditingProduct(null)
            handleSaved()
          }}
        />
      )}
      {statusTarget && (
        <ProductStatusDialog
          product={statusTarget}
          onOpenChange={(open) => {
            if (!open) setStatusTarget(null)
          }}
          onChanged={handleStatusChanged}
        />
      )}
    </div>
  )
}
