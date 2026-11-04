import { useState, type FormEvent } from "react"
import { useMediaQuery } from "react-responsive"
import { PlusIcon, Trash2Icon } from "lucide-react"

import {
  createProduct,
  updateProduct,
  type Product,
  type ProductBrand,
  type ProductCategory,
  type ProductUnit,
} from "@/services/products-api"
import { getApiErrorMessage } from "@/lib/api"
import { toast } from "@/components/ui/toast"
import { Button } from "@/components/ui/button"
import {
  Field,
  FieldDescription,
  FieldError,
  FieldLabel,
  FieldLegend,
  FieldSet,
} from "@/components/ui/field"
import { Input } from "@/components/ui/input"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Textarea } from "@/components/ui/textarea"
import {
  createEmptyProductForm,
  createEmptyVariant,
  toProductFormValues,
  toProductInput,
  toProductUpdateInput,
  validateProductForm,
  type ProductFormErrors,
  type ProductFormValues,
  type VariantFormValues,
} from "@/lib/product-validation"
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetDescription,
} from "@/components/ui/sheet"
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
} from "@/components/ui/dialog"

interface ProductFormSheetProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  product?: Product | null
  brands: ProductBrand[]
  units: ProductUnit[]
  categories: ProductCategory[]
  onSaved: (product: Product, mode: "create" | "edit") => void
}

const NO_CATEGORY = "__none__"

export function ProductFormSheet({
  open,
  onOpenChange,
  product,
  brands,
  units,
  categories,
  onSaved,
}: ProductFormSheetProps) {
  const [values, setValues] = useState<ProductFormValues>(() =>
    product ? toProductFormValues(product) : createEmptyProductForm(),
  )
  const [errors, setErrors] = useState<ProductFormErrors>({})
  const [formError, setFormError] = useState("")
  const [saving, setSaving] = useState(false)

  const mode = product ? "edit" : "create"
  const isMobile = useMediaQuery({ query: "(max-width: 768px)" })

  const brandItems = Object.fromEntries(
    brands.map((brand) => [String(brand.id), brand.name]),
  )
  const unitItems = Object.fromEntries(
    units.map((unit) => [String(unit.id), unit.code]),
  )
  const categoryItems = {
    [NO_CATEGORY]: "No category",
    ...Object.fromEntries(
      categories.map((category) => [String(category.id), category.name]),
    ),
  }

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

  function setVariantField(
    index: number,
    field: keyof VariantFormValues,
    value: string,
  ) {
    setValues((current) => ({
      ...current,
      variants: current.variants.map((variant, variantIndex) =>
        variantIndex === index ? { ...variant, [field]: value } : variant,
      ),
    }))
    setErrors((current) => {
      const next = { ...current }

      if (next.variantsMessage) delete next.variantsMessage

      if (next.variants) {
        const variants = [...next.variants]
        const variantErrors = { ...variants[index] }

        delete variantErrors[field]
        variants[index] = variantErrors
        next.variants = variants
      }

      return next
    })
    setFormError("")
  }

  function addVariant() {
    setValues((current) => ({
      ...current,
      variants: [...current.variants, createEmptyVariant()],
    }))
    setErrors((current) => ({
      ...current,
      variants: undefined,
      variantsMessage: undefined,
    }))
  }

  function removeVariant(index: number) {
    setValues((current) => ({
      ...current,
      variants: current.variants.filter((_, variantIndex) => variantIndex !== index),
    }))
    setErrors((current) => ({
      ...current,
      variants: undefined,
      variantsMessage: undefined,
    }))
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
      saved = product
        ? await updateProduct(product.id, toProductUpdateInput(values))
        : await createProduct(toProductInput(values))
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
      description: saved.attributes.name,
      type: "success",
    })
    onOpenChange(false)
    onSaved(saved, mode)
  }

  const variantErrors = errors.variants ?? []
  const nameError = errors.name

  const formContent = (
    <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-6">
      <div className="grid gap-4 sm:grid-cols-2">
        <Field className="sm:col-span-2" data-invalid={!!nameError}>
          <FieldLabel htmlFor="product-name">Name</FieldLabel>
          <Input
            id="product-name"
            value={values.name}
            onChange={(event) => setField("name", event.target.value)}
            aria-invalid={nameError ? true : undefined}
            aria-describedby={nameError ? "product-name-error" : undefined}
          />
          {nameError && <FieldError id="product-name-error">{nameError}</FieldError>}
        </Field>

        <Field data-invalid={!!errors.brandId}>
          <FieldLabel htmlFor="product-brand">Brand</FieldLabel>
          <Select
            items={brandItems}
            value={values.brandId}
            onValueChange={(value) => setField("brandId", String(value ?? ""))}
          >
            <SelectTrigger
              id="product-brand"
              className="w-full"
              aria-invalid={errors.brandId ? true : undefined}
            >
              <SelectValue placeholder="Select a brand" />
            </SelectTrigger>
            <SelectContent>
              {brands.map((brand) => (
                <SelectItem key={brand.id} value={String(brand.id)}>
                  {brand.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          {errors.brandId && <FieldError>{errors.brandId}</FieldError>}
        </Field>

        <Field data-invalid={!!errors.unitId}>
          <FieldLabel htmlFor="product-unit">Unit</FieldLabel>
          <Select
            items={unitItems}
            value={values.unitId}
            onValueChange={(value) => setField("unitId", String(value ?? ""))}
          >
            <SelectTrigger
              id="product-unit"
              className="w-full"
              aria-invalid={errors.unitId ? true : undefined}
            >
              <SelectValue placeholder="Select a unit" />
            </SelectTrigger>
            <SelectContent>
              {units.map((unit) => (
                <SelectItem key={unit.id} value={String(unit.id)}>
                  {unit.code}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          {errors.unitId && <FieldError>{errors.unitId}</FieldError>}
        </Field>

        <Field data-invalid={!!errors.categoryId}>
          <FieldLabel htmlFor="product-category">Category</FieldLabel>
          <Select
            items={categoryItems}
            value={values.categoryId || NO_CATEGORY}
            onValueChange={(value) =>
              setField("categoryId", value === NO_CATEGORY ? "" : String(value))
            }
          >
            <SelectTrigger
              id="product-category"
              className="w-full"
              aria-invalid={errors.categoryId ? true : undefined}
            >
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value={NO_CATEGORY}>No category</SelectItem>
              {categories.map((category) => (
                <SelectItem key={category.id} value={String(category.id)}>
                  {category.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          {errors.categoryId && <FieldError>{errors.categoryId}</FieldError>}
        </Field>

        <Field className="sm:col-span-2" data-invalid={!!errors.description}>
          <FieldLabel htmlFor="product-description">Description</FieldLabel>
          <Textarea
            id="product-description"
            value={values.description}
            onChange={(event) => setField("description", event.target.value)}
            aria-invalid={errors.description ? true : undefined}
          />
          {errors.description && (
            <FieldError>{errors.description}</FieldError>
          )}
        </Field>
      </div>

      {mode === "edit" && product ? (
        <FieldSet>
          <FieldLegend>Variants</FieldLegend>
          <FieldDescription>Variants can’t be changed yet.</FieldDescription>
          <div className="flex flex-col gap-2">
            {product.attributes.variants.map((variant) => (
              <div
                key={variant.id}
                className="flex items-center justify-between border p-3 text-sm"
              >
                <div className="flex flex-col">
                  <span className="font-medium">{variant.sku}</span>
                  {variant.options && (
                    <span className="text-xs text-muted-foreground">
                      {Object.entries(variant.options)
                        .map(([key, value]) => `${key}: ${value}`)
                        .join(" · ")}
                    </span>
                  )}
                </div>
                <span className="font-mono tabular-nums">
                  {variant.sellingPrice}
                </span>
              </div>
            ))}
          </div>
        </FieldSet>
      ) : (
        <FieldSet>
          <div className="flex items-center justify-between">
            <FieldLegend className="mb-0">
              Variants ({values.variants.length})
            </FieldLegend>
            <Button type="button" variant="outline" size="sm" onClick={addVariant}>
              <PlusIcon />
              Add variant
            </Button>
          </div>

          {errors.variantsMessage && (
            <FieldError>{errors.variantsMessage}</FieldError>
          )}

          <div className="flex flex-col gap-4">
            {values.variants.map((variant, index) => {
              const variantError = variantErrors[index] ?? {}

              return (
                <div key={index} className="border p-4">
                  <div className="mb-3 flex items-center justify-between">
                    <span className="text-sm font-semibold uppercase tracking-wider">
                      Variant {index + 1}
                    </span>
                    {values.variants.length > 1 && (
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        onClick={() => removeVariant(index)}
                        aria-label={`Remove variant ${index + 1}`}
                      >
                        <Trash2Icon />
                      </Button>
                    )}
                  </div>

                  <div className="grid gap-4 sm:grid-cols-2">
                    <Field data-invalid={!!variantError.sku}>
                      <FieldLabel htmlFor={`variant-${index}-sku`}>SKU</FieldLabel>
                      <Input
                        id={`variant-${index}-sku`}
                        value={variant.sku}
                        onChange={(event) =>
                          setVariantField(index, "sku", event.target.value)
                        }
                        aria-invalid={variantError.sku ? true : undefined}
                      />
                      {variantError.sku && (
                        <FieldError>{variantError.sku}</FieldError>
                      )}
                    </Field>

                    <Field data-invalid={!!variantError.barcode}>
                      <FieldLabel htmlFor={`variant-${index}-barcode`}>
                        Barcode
                      </FieldLabel>
                      <Input
                        id={`variant-${index}-barcode`}
                        value={variant.barcode}
                        onChange={(event) =>
                          setVariantField(index, "barcode", event.target.value)
                        }
                        aria-invalid={variantError.barcode ? true : undefined}
                      />
                      {variantError.barcode && (
                        <FieldError>{variantError.barcode}</FieldError>
                      )}
                    </Field>

                    <Field data-invalid={!!variantError.costPrice}>
                      <FieldLabel htmlFor={`variant-${index}-cost`}>
                        Cost price
                      </FieldLabel>
                      <Input
                        id={`variant-${index}-cost`}
                        inputMode="decimal"
                        value={variant.costPrice}
                        onChange={(event) =>
                          setVariantField(index, "costPrice", event.target.value)
                        }
                        aria-invalid={variantError.costPrice ? true : undefined}
                      />
                      {variantError.costPrice && (
                        <FieldError>{variantError.costPrice}</FieldError>
                      )}
                    </Field>

                    <Field data-invalid={!!variantError.sellingPrice}>
                      <FieldLabel htmlFor={`variant-${index}-selling`}>
                        Selling price
                      </FieldLabel>
                      <Input
                        id={`variant-${index}-selling`}
                        inputMode="decimal"
                        value={variant.sellingPrice}
                        onChange={(event) =>
                          setVariantField(
                            index,
                            "sellingPrice",
                            event.target.value,
                          )
                        }
                        aria-invalid={
                          variantError.sellingPrice ? true : undefined
                        }
                      />
                      {variantError.sellingPrice && (
                        <FieldError>{variantError.sellingPrice}</FieldError>
                      )}
                    </Field>
                  </div>
                </div>
              )
            })}
          </div>
        </FieldSet>
      )}

      {formError && <FieldError id="product-form-error">{formError}</FieldError>}

      <div className="flex justify-end gap-2">
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
      </div>
    </form>
  )

  const title = mode === "edit" ? "Edit product" : "Add product"
  const description =
    mode === "edit"
      ? `Update the details for ${product?.attributes.name}.`
      : "Add a product and one or more variants."

  if (isMobile) {
    return (
      <Sheet open={open} onOpenChange={onOpenChange}>
        <SheetContent side="bottom" className="max-h-[85vh] overflow-y-auto">
          <SheetHeader>
            <SheetTitle>{title}</SheetTitle>
            <SheetDescription>{description}</SheetDescription>
          </SheetHeader>
          {formContent}
        </SheetContent>
      </Sheet>
    )
  }

  return (
    <Dialog
      open={open}
      onOpenChange={(nextOpen) => {
        if (!saving) onOpenChange(nextOpen)
      }}
    >
      <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-xl">
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
          <DialogDescription>{description}</DialogDescription>
        </DialogHeader>
        {formContent}
      </DialogContent>
    </Dialog>
  )
}
