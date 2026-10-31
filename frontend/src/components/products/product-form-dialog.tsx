import { useState, type FormEvent } from "react"

import {
  createProduct,
  updateProduct,
  PRODUCT_STATUS,
  type Product,
  type ProductStatus,
} from "@/services/products-api"
import { getApiErrorMessage } from "@/lib/api"
import {
  createEmptyProductForm,
  toProductFormValues,
  toProductInput,
  validateProductForm,
  type ProductFormErrors,
  type ProductFormValues,
} from "@/lib/product-validation"
import { toast } from "@/components/ui/toast"
import { Button } from "@/components/ui/button"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { Field, FieldError, FieldLabel } from "@/components/ui/field"
import { Input } from "@/components/ui/input"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Textarea } from "@/components/ui/textarea"

interface ProductFormDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  product?: Product | null
  onSaved: (product: Product, mode: "create" | "edit") => void
}

export function ProductFormDialog({
  open,
  onOpenChange,
  product,
  onSaved,
}: ProductFormDialogProps) {
  const [values, setValues] = useState<ProductFormValues>(() =>
    product ? toProductFormValues(product) : createEmptyProductForm(),
  )
  const [errors, setErrors] = useState<ProductFormErrors>({})
  const [formError, setFormError] = useState("")
  const [saving, setSaving] = useState(false)

  const mode = product ? "edit" : "create"

  function setField<K extends keyof ProductFormValues>(
    field: K,
    value: ProductFormValues[K],
  ) {
    setValues((current) => ({ ...current, [field]: value }))
    setErrors((current) => {
      if (!current[field]) return current

      const next = { ...current }
      delete next[field]
      return next
    })
    setFormError("")
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFormError("")

    const validationErrors = validateProductForm(values)

    if (Object.keys(validationErrors).length > 0) {
      setErrors(validationErrors)
      return
    }

    setErrors({})
    setSaving(true)

    let saved: Product
    try {
      const input = toProductInput(values)
      saved = product
        ? await updateProduct(product.id, input)
        : await createProduct(input)
    } catch (cause) {
      const message = getApiErrorMessage(cause, "Unable to save product.")

      setFormError(message)
      toast.add({
        title: "Unable to save product",
        description: message,
        type: "error",
      })
      return
    } finally {
      setSaving(false)
    }

    toast.add({
      title: mode === "edit" ? "Product updated" : "Product created",
      description: saved.name,
      type: "success",
    })
    onOpenChange(false)
    onSaved(saved, mode)
  }

  return (
    <Dialog
      open={open}
      onOpenChange={(nextOpen) => {
        if (!saving) onOpenChange(nextOpen)
      }}
    >
      <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>
            {mode === "edit" ? "Edit product" : "Add product"}
          </DialogTitle>
          <DialogDescription>
            {mode === "edit"
              ? `Update the details for ${product?.name}.`
              : "Add a new product to your catalog."}
          </DialogDescription>
        </DialogHeader>
        <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-6">
          <div className="grid gap-4 sm:grid-cols-2">
            <Field data-invalid={!!errors.name}>
              <FieldLabel htmlFor="product-name">Name</FieldLabel>
              <Input
                id="product-name"
                value={values.name}
                onChange={(event) => setField("name", event.target.value)}
                aria-invalid={errors.name ? true : undefined}
                aria-describedby={errors.name ? "product-name-error" : undefined}
              />
              {errors.name && (
                <FieldError id="product-name-error">{errors.name}</FieldError>
              )}
            </Field>

            <Field data-invalid={!!errors.sku}>
              <FieldLabel htmlFor="product-sku">SKU</FieldLabel>
              <Input
                id="product-sku"
                value={values.sku}
                onChange={(event) => setField("sku", event.target.value)}
                aria-invalid={errors.sku ? true : undefined}
                aria-describedby={errors.sku ? "product-sku-error" : undefined}
              />
              {errors.sku && (
                <FieldError id="product-sku-error">{errors.sku}</FieldError>
              )}
            </Field>

            <Field data-invalid={!!errors.brand}>
              <FieldLabel htmlFor="product-brand">Brand</FieldLabel>
              <Input
                id="product-brand"
                value={values.brand}
                onChange={(event) => setField("brand", event.target.value)}
                aria-invalid={errors.brand ? true : undefined}
                aria-describedby={
                  errors.brand ? "product-brand-error" : undefined
                }
              />
              {errors.brand && (
                <FieldError id="product-brand-error">{errors.brand}</FieldError>
              )}
            </Field>

            <Field data-invalid={!!errors.unit}>
              <FieldLabel htmlFor="product-unit">Unit</FieldLabel>
              <Input
                id="product-unit"
                value={values.unit}
                onChange={(event) => setField("unit", event.target.value)}
                aria-invalid={errors.unit ? true : undefined}
                aria-describedby={errors.unit ? "product-unit-error" : undefined}
              />
              {errors.unit && (
                <FieldError id="product-unit-error">{errors.unit}</FieldError>
              )}
            </Field>

            <Field data-invalid={!!errors.sellingPrice}>
              <FieldLabel htmlFor="product-selling-price">
                Selling price
              </FieldLabel>
              <Input
                id="product-selling-price"
                type="number"
                min="0"
                step="1"
                value={values.sellingPrice}
                onChange={(event) =>
                  setField("sellingPrice", event.target.value)
                }
                aria-invalid={errors.sellingPrice ? true : undefined}
                aria-describedby={
                  errors.sellingPrice
                    ? "product-selling-price-error"
                    : undefined
                }
              />
              {errors.sellingPrice && (
                <FieldError id="product-selling-price-error">
                  {errors.sellingPrice}
                </FieldError>
              )}
            </Field>

            <Field data-invalid={!!errors.costPrice}>
              <FieldLabel htmlFor="product-cost-price">Cost price</FieldLabel>
              <Input
                id="product-cost-price"
                type="number"
                min="0"
                step="1"
                value={values.costPrice}
                onChange={(event) => setField("costPrice", event.target.value)}
                aria-invalid={errors.costPrice ? true : undefined}
                aria-describedby={
                  errors.costPrice ? "product-cost-price-error" : undefined
                }
              />
              {errors.costPrice && (
                <FieldError id="product-cost-price-error">
                  {errors.costPrice}
                </FieldError>
              )}
            </Field>

            <Field data-invalid={!!errors.imageUrl}>
              <FieldLabel htmlFor="product-image-url">Image URL</FieldLabel>
              <Input
                id="product-image-url"
                type="url"
                placeholder="https://"
                value={values.imageUrl}
                onChange={(event) => setField("imageUrl", event.target.value)}
                aria-invalid={errors.imageUrl ? true : undefined}
                aria-describedby={
                  errors.imageUrl ? "product-image-url-error" : undefined
                }
              />
              {errors.imageUrl && (
                <FieldError id="product-image-url-error">
                  {errors.imageUrl}
                </FieldError>
              )}
            </Field>

            <Field data-invalid={!!errors.status}>
              <FieldLabel htmlFor="product-status">Status</FieldLabel>
              <Select
                items={PRODUCT_STATUS}
                value={values.status}
                onValueChange={(value) =>
                  setField("status", (value as ProductStatus) ?? "active")
                }
              >
                <SelectTrigger
                  id="product-status"
                  className="w-full"
                  aria-invalid={errors.status ? true : undefined}
                  aria-describedby={
                    errors.status ? "product-status-error" : undefined
                  }
                >
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {Object.entries(PRODUCT_STATUS).map(([value, label]) => (
                    <SelectItem key={value} value={value}>
                      {label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {errors.status && (
                <FieldError id="product-status-error">{errors.status}</FieldError>
              )}
            </Field>

            <Field className="sm:col-span-2" data-invalid={!!errors.description}>
              <FieldLabel htmlFor="product-description">
                Description
              </FieldLabel>
              <Textarea
                id="product-description"
                value={values.description}
                onChange={(event) => setField("description", event.target.value)}
                aria-invalid={errors.description ? true : undefined}
                aria-describedby={
                  errors.description ? "product-description-error" : undefined
                }
              />
              {errors.description && (
                <FieldError id="product-description-error">
                  {errors.description}
                </FieldError>
              )}
            </Field>
          </div>

          {product?.category && (
            <p className="text-sm text-muted-foreground">
              Category: {product.category.name} (read-only)
            </p>
          )}

          {formError && <FieldError id="product-form-error">{formError}</FieldError>}

          <DialogFooter>
            <Button
              type="button"
              variant="outline"
              onClick={() => onOpenChange(false)}
              disabled={saving}
            >
              Cancel
            </Button>
            <Button type="submit" disabled={saving}>
              {mode === "edit" ? "Save changes" : "Create product"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
