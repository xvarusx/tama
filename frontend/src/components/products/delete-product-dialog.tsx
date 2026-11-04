import { useState } from "react"

import { deleteProduct, type Product } from "@/services/products-api"
import { getApiErrorMessage } from "@/lib/api"
import { toast } from "@/components/ui/toast"
import { FieldError } from "@/components/ui/field"
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog"

interface DeleteProductDialogProps {
  product: Product
  onOpenChange: (open: boolean) => void
  onDeleted: () => void
}

export function DeleteProductDialog({
  product,
  onOpenChange,
  onDeleted,
}: DeleteProductDialogProps) {
  const [deleting, setDeleting] = useState(false)
  const [error, setError] = useState("")

  async function handleDelete() {
    setDeleting(true)
    setError("")

    try {
      await deleteProduct(product.id)
    } catch (cause) {
      const message = getApiErrorMessage(cause, "Unable to delete product.")

      setError(message)
      toast.add({
        title: "Unable to delete product",
        description: message,
        type: "error",
      })
      return
    } finally {
      setDeleting(false)
    }

    toast.add({
      title: "Product deleted",
      description: product.attributes.name,
      type: "success",
    })
    onOpenChange(false)
    onDeleted()
  }

  return (
    <AlertDialog
      open
      onOpenChange={(nextOpen) => {
        if (!deleting) onOpenChange(nextOpen)
      }}
    >
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>Delete product</AlertDialogTitle>
          <AlertDialogDescription>
            {product.attributes.name} and its{" "}
            {product.attributes.variants.length} variant
            {product.attributes.variants.length === 1 ? "" : "s"} will be
            permanently deleted. This cannot be undone.
          </AlertDialogDescription>
        </AlertDialogHeader>
        {error && <FieldError>{error}</FieldError>}
        <AlertDialogFooter>
          <AlertDialogCancel disabled={deleting}>Cancel</AlertDialogCancel>
          <AlertDialogAction
            variant="destructive"
            disabled={deleting}
            onClick={handleDelete}
          >
            Delete
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  )
}
