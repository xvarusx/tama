import { useState } from "react"

import {
  setProductStatus,
  type Product,
  type ProductStatus,
} from "@/services/products-api"
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

interface ProductStatusDialogProps {
  product: Product
  onOpenChange: (open: boolean) => void
  onChanged: (product: Product) => void
}

export function ProductStatusDialog({
  product,
  onOpenChange,
  onChanged,
}: ProductStatusDialogProps) {
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState("")

  const nextStatus: ProductStatus =
    product.status === "archive" ? "active" : "archive"
  const archiving = nextStatus === "archive"

  async function handleChange() {
    setSaving(true)
    setError("")

    let updated: Product
    try {
      updated = await setProductStatus(product, nextStatus)
    } catch (cause) {
      const message = getApiErrorMessage(
        cause,
        "Unable to change product status.",
      )

      setError(message)
      toast.add({
        title: "Unable to change product status",
        description: message,
        type: "error",
      })
      return
    } finally {
      setSaving(false)
    }

    toast.add({
      title: archiving ? "Product archived" : "Product activated",
      description: product.name,
      type: "success",
    })
    onOpenChange(false)
    onChanged(updated)
  }

  return (
    <AlertDialog
      open
      onOpenChange={(nextOpen) => {
        if (!saving) onOpenChange(nextOpen)
      }}
    >
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>
            {archiving ? "Archive product" : "Activate product"}
          </AlertDialogTitle>
          <AlertDialogDescription>
            {archiving
              ? `${product.name} will be archived and hidden from the active catalog.`
              : `${product.name} will be active again.`}
          </AlertDialogDescription>
        </AlertDialogHeader>
        {error && <FieldError>{error}</FieldError>}
        <AlertDialogFooter>
          <AlertDialogCancel disabled={saving}>Cancel</AlertDialogCancel>
          <AlertDialogAction
            variant={archiving ? "destructive" : "default"}
            disabled={saving}
            onClick={handleChange}
          >
            {archiving ? "Archive" : "Activate"}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  )
}
