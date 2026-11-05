import { useRef, useState, type ChangeEvent, type FormEvent } from "react"
import { useMediaQuery } from "react-responsive"
import { ImageIcon, PlusIcon, Trash2Icon, XIcon } from "lucide-react"

import {
  createBrand,
  createCategory,
  createProduct,
  deleteProductMedia,
  productMediaUrl,
  updateProduct,
  uploadProductMedia,
  type Product,
  type ProductBrand,
  type ProductCategory,
  type ProductMedia,
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
  onReferenceCreated?: (
    kind: "brand" | "category",
    item: ProductBrand | ProductCategory,
  ) => void
}

const NO_CATEGORY = "__none__"
const ALLOWED_IMAGE_TYPES = [
  "image/jpeg",
  "image/png",
  "image/webp",
  "image/svg+xml",
]
const MAX_IMAGE_BYTES = 5 * 1024 * 1024

interface PendingImage {
  file: File
  preview: string
}

export function ProductFormSheet({
  open,
  onOpenChange,
  product,
  brands,
  units,
  categories,
  onSaved,
  onReferenceCreated,
}: ProductFormSheetProps) {
  const [values, setValues] = useState<ProductFormValues>(() =>
    product ? toProductFormValues(product) : createEmptyProductForm(),
  )
  const [errors, setErrors] = useState<ProductFormErrors>({})
  const [formError, setFormError] = useState("")
  const [saving, setSaving] = useState(false)

  const [brandOptions, setBrandOptions] = useState(brands)
  const [categoryOptions, setCategoryOptions] = useState(categories)

  const [media, setMedia] = useState<ProductMedia[]>(
    product?.attributes.media ?? [],
  )
  const [removedMediaIds, setRemovedMediaIds] = useState<number[]>([])
  const [pendingImages, setPendingImages] = useState<PendingImage[]>([])
  const [imageError, setImageError] = useState("")
  const fileInputRef = useRef<HTMLInputElement>(null)

  const [showBrandCreate, setShowBrandCreate] = useState(false)
  const [newBrandName, setNewBrandName] = useState("")
  const [brandCreateError, setBrandCreateError] = useState("")
  const [creatingBrand, setCreatingBrand] = useState(false)

  const [showCategoryCreate, setShowCategoryCreate] = useState(false)
  const [newCategoryName, setNewCategoryName] = useState("")
  const [categoryCreateError, setCategoryCreateError] = useState("")
  const [creatingCategory, setCreatingCategory] = useState(false)

  const mode = product ? "edit" : "create"
  const isMobile = useMediaQuery({ query: "(max-width: 768px)" })

  const brandItems = Object.fromEntries(
    brandOptions.map((brand) => [String(brand.id), brand.name]),
  )
  const unitItems = Object.fromEntries(
    units.map((unit) => [String(unit.id), unit.code]),
  )
  const categoryItems = {
    [NO_CATEGORY]: "No category",
    ...Object.fromEntries(
      categoryOptions.map((category) => [
        String(category.id),
        category.name,
      ]),
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
    field: "sku" | "costPrice" | "sellingPrice" | "barcode",
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
      variants: current.variants.filter(
        (_, variantIndex) => variantIndex !== index,
      ),
    }))
    setErrors((current) => ({
      ...current,
      variants: undefined,
      variantsMessage: undefined,
    }))
  }

  function handleImages(event: ChangeEvent<HTMLInputElement>) {
    const files = Array.from(event.target.files ?? [])
    const accepted: PendingImage[] = []
    let message = ""

    for (const file of files) {
      if (!ALLOWED_IMAGE_TYPES.includes(file.type)) {
        message = "Only JPEG, PNG, WebP and SVG images are supported."
        continue
      }
      if (file.size > MAX_IMAGE_BYTES) {
        message = "Each image must be smaller than 5 MB."
        continue
      }

      accepted.push({ file, preview: URL.createObjectURL(file) })
    }

    if (accepted.length > 0) {
      setPendingImages((current) => [...current, ...accepted])
    }
    setImageError(message)
    event.target.value = ""
  }

  function removePendingImage(index: number) {
    setPendingImages((current) => {
      const image = current[index]
      if (image) URL.revokeObjectURL(image.preview)

      return current.filter((_, imageIndex) => imageIndex !== index)
    })
  }

  function removeExistingImage(mediaId: number) {
    setMedia((current) => current.filter((item) => item.id !== mediaId))
    setRemovedMediaIds((current) =>
      current.includes(mediaId) ? current : [...current, mediaId],
    )
  }

  async function handleCreateBrand() {
    const name = newBrandName.trim()
    if (!name) {
      setBrandCreateError("Enter a brand name.")
      return
    }

    setCreatingBrand(true)
    setBrandCreateError("")
    try {
      const brand = await createBrand(name)
      setBrandOptions((current) =>
        [...current, brand].sort((a, b) => a.name.localeCompare(b.name)),
      )
      setField("brandId", String(brand.id))
      setNewBrandName("")
      setShowBrandCreate(false)
      onReferenceCreated?.("brand", brand)
      toast.add({ title: "Brand created", description: brand.name, type: "success" })
    } catch (cause) {
      const message = getApiErrorMessage(cause, "Unable to create brand.")
      setBrandCreateError(message)
      toast.add({ title: "Unable to create brand", description: message, type: "error" })
    } finally {
      setCreatingBrand(false)
    }
  }

  async function handleCreateCategory() {
    const name = newCategoryName.trim()
    if (!name) {
      setCategoryCreateError("Enter a category name.")
      return
    }

    setCreatingCategory(true)
    setCategoryCreateError("")
    try {
      const category = await createCategory(name)
      setCategoryOptions((current) =>
        [...current, category].sort((a, b) => a.name.localeCompare(b.name)),
      )
      setField("categoryId", String(category.id))
      setNewCategoryName("")
      setShowCategoryCreate(false)
      onReferenceCreated?.("category", category)
      toast.add({
        title: "Category created",
        description: category.name,
        type: "success",
      })
    } catch (cause) {
      const message = getApiErrorMessage(cause, "Unable to create category.")
      setCategoryCreateError(message)
      toast.add({
        title: "Unable to create category",
        description: message,
        type: "error",
      })
    } finally {
      setCreatingCategory(false)
    }
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
      setSaving(false)
      return
    }

    const imageProblems: string[] = []

    for (const mediaId of removedMediaIds) {
      try {
        await deleteProductMedia(mediaId)
      } catch (cause) {
        imageProblems.push(
          getApiErrorMessage(cause, "Unable to remove an image."),
        )
      }
    }

    for (const image of pendingImages) {
      try {
        await uploadProductMedia(saved.id, image.file)
      } catch (cause) {
        imageProblems.push(getApiErrorMessage(cause, "Unable to upload an image."))
      }
    }

    if (imageProblems.length > 0) {
      toast.add({
        title: "Product saved, but some images failed",
        description: imageProblems[0],
        type: "warning",
      })
    } else {
      toast.add({
        title: mode === "edit" ? "Product updated" : "Product created",
        description: saved.attributes.name,
        type: "success",
      })
    }

    setSaving(false)
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
          <div className="flex items-center justify-between gap-2">
            <FieldLabel htmlFor="product-brand">Brand</FieldLabel>
            <Button
              type="button"
              variant="ghost"
              size="sm"
              className="h-auto px-1 text-xs"
              onClick={() => {
                setShowBrandCreate((current) => !current)
                setBrandCreateError("")
              }}
            >
              <PlusIcon />
              New
            </Button>
          </div>
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
              {brandOptions.map((brand) => (
                <SelectItem key={brand.id} value={String(brand.id)}>
                  {brand.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          {showBrandCreate && (
            <div className="flex flex-col gap-2 border p-3">
              <Input
                value={newBrandName}
                onChange={(event) => setNewBrandName(event.target.value)}
                placeholder="Brand name"
                aria-label="New brand name"
                onKeyDown={(event) => {
                  if (event.key === "Enter") {
                    event.preventDefault()
                    void handleCreateBrand()
                  }
                }}
              />
              <div className="flex gap-2">
                <Button
                  type="button"
                  size="sm"
                  onClick={() => void handleCreateBrand()}
                  disabled={creatingBrand}
                >
                  {creatingBrand ? "Creating…" : "Create brand"}
                </Button>
                <Button
                  type="button"
                  size="sm"
                  variant="ghost"
                  onClick={() => setShowBrandCreate(false)}
                  disabled={creatingBrand}
                >
                  Cancel
                </Button>
              </div>
              {brandCreateError && <FieldError>{brandCreateError}</FieldError>}
            </div>
          )}
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

        <Field className="sm:col-span-2" data-invalid={!!errors.categoryId}>
          <div className="flex items-center justify-between gap-2">
            <FieldLabel htmlFor="product-category">Category</FieldLabel>
            <Button
              type="button"
              variant="ghost"
              size="sm"
              className="h-auto px-1 text-xs"
              onClick={() => {
                setShowCategoryCreate((current) => !current)
                setCategoryCreateError("")
              }}
            >
              <PlusIcon />
              New
            </Button>
          </div>
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
              {categoryOptions.map((category) => (
                <SelectItem key={category.id} value={String(category.id)}>
                  {category.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          {showCategoryCreate && (
            <div className="flex flex-col gap-2 border p-3">
              <Input
                value={newCategoryName}
                onChange={(event) => setNewCategoryName(event.target.value)}
                placeholder="Category name"
                aria-label="New category name"
                onKeyDown={(event) => {
                  if (event.key === "Enter") {
                    event.preventDefault()
                    void handleCreateCategory()
                  }
                }}
              />
              <div className="flex gap-2">
                <Button
                  type="button"
                  size="sm"
                  onClick={() => void handleCreateCategory()}
                  disabled={creatingCategory}
                >
                  {creatingCategory ? "Creating…" : "Create category"}
                </Button>
                <Button
                  type="button"
                  size="sm"
                  variant="ghost"
                  onClick={() => setShowCategoryCreate(false)}
                  disabled={creatingCategory}
                >
                  Cancel
                </Button>
              </div>
              {categoryCreateError && (
                <FieldError>{categoryCreateError}</FieldError>
              )}
            </div>
          )}
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

      <FieldSet>
        <div className="flex items-center justify-between">
          <FieldLegend className="mb-0">Images</FieldLegend>
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={() => fileInputRef.current?.click()}
          >
            <ImageIcon />
            Add images
          </Button>
        </div>
        <FieldDescription>
          JPEG, PNG, WebP or SVG, up to 5 MB each. The first image is the cover.
        </FieldDescription>
        <input
          ref={fileInputRef}
          type="file"
          multiple
          accept="image/jpeg,image/png,image/webp,image/svg+xml"
          className="sr-only"
          onChange={handleImages}
        />
        {(media.length > 0 || pendingImages.length > 0) && (
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
            {media.map((item) => (
              <div key={`media-${item.id}`} className="relative border">
                <img
                  src={productMediaUrl(item)}
                  alt=""
                  className="aspect-square w-full object-cover"
                />
                <Button
                  type="button"
                  variant="destructive"
                  size="icon-sm"
                  className="absolute top-1 right-1"
                  onClick={() => removeExistingImage(item.id)}
                  aria-label="Remove image"
                >
                  <XIcon />
                </Button>
              </div>
            ))}
            {pendingImages.map((image, index) => (
              <div key={image.preview} className="relative border">
                <img
                  src={image.preview}
                  alt=""
                  className="aspect-square w-full object-cover"
                />
                <Button
                  type="button"
                  variant="destructive"
                  size="icon-sm"
                  className="absolute top-1 right-1"
                  onClick={() => removePendingImage(index)}
                  aria-label="Remove new image"
                >
                  <XIcon />
                </Button>
              </div>
            ))}
          </div>
        )}
        {imageError && <FieldError>{imageError}</FieldError>}
      </FieldSet>

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
              <div key={variant.id ?? `new-${index}`} className="border p-4">
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
      : "Add a product, its variants and its images."

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
