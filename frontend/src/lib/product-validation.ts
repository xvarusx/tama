import type {
  Product,
  ProductInput,
  ProductUpdateInput,
} from "@/services/products-api"

export interface VariantFormValues {
  sku: string
  costPrice: string
  sellingPrice: string
  barcode: string
}

export interface ProductFormValues {
  name: string
  brandId: string
  unitId: string
  categoryId: string
  description: string
  variants: VariantFormValues[]
}

export interface VariantFormErrors {
  sku?: string
  costPrice?: string
  sellingPrice?: string
  barcode?: string
}

export interface ProductFormErrors {
  name?: string
  brandId?: string
  unitId?: string
  categoryId?: string
  description?: string
  variantsMessage?: string
  variants?: VariantFormErrors[]
}

const MONEY = /^\d{1,10}(\.\d{1,2})?$/

export function createEmptyVariant(): VariantFormValues {
  return { sku: "", costPrice: "", sellingPrice: "", barcode: "" }
}

export function createEmptyProductForm(): ProductFormValues {
  return {
    name: "",
    brandId: "",
    unitId: "",
    categoryId: "",
    description: "",
    variants: [createEmptyVariant()],
  }
}

export function toProductFormValues(product: Product): ProductFormValues {
  return {
    name: product.attributes.name,
    brandId: String(product.attributes.brand.id),
    unitId: String(product.attributes.unit.id),
    categoryId: product.attributes.category
      ? String(product.attributes.category.id)
      : "",
    description: product.attributes.description ?? "",
    variants: product.attributes.variants.map((variant) => ({
      sku: variant.sku,
      costPrice: variant.costPrice ?? "",
      sellingPrice: variant.sellingPrice,
      barcode: variant.barcode ?? "",
    })),
  }
}

function validateVariant(
  variant: VariantFormValues,
  seenSkus: Set<string>,
): VariantFormErrors {
  const errors: VariantFormErrors = {}
  const sku = variant.sku.trim()

  if (!sku) {
    errors.sku = "SKU is required."
  } else {
    const key = sku.toLowerCase()

    if (seenSkus.has(key)) {
      errors.sku = "This SKU is used by another variant."
    } else {
      seenSkus.add(key)
    }
  }

  const costPrice = variant.costPrice.trim()
  if (!costPrice) {
    errors.costPrice = "Cost price is required."
  } else if (!MONEY.test(costPrice)) {
    errors.costPrice = "Use a number with at most 2 decimals."
  }

  const sellingPrice = variant.sellingPrice.trim()
  if (!sellingPrice) {
    errors.sellingPrice = "Selling price is required."
  } else if (!MONEY.test(sellingPrice)) {
    errors.sellingPrice = "Use a number with at most 2 decimals."
  }

  return errors
}

export function validateProductForm(
  values: ProductFormValues,
): ProductFormErrors {
  const errors: ProductFormErrors = {}

  if (!values.name.trim()) errors.name = "Name is required."
  if (!values.brandId.trim()) errors.brandId = "Select a brand."
  if (!values.unitId.trim()) errors.unitId = "Select a unit."

  if (values.variants.length === 0) {
    errors.variantsMessage = "Add at least one variant."
    return errors
  }

  const seenSkus = new Set<string>()
  const variantErrors = values.variants.map((variant) =>
    validateVariant(variant, seenSkus),
  )

  if (variantErrors.some((variant) => Object.keys(variant).length > 0)) {
    errors.variants = variantErrors
  }

  return errors
}

function toNullable(value: string): string | null {
  const trimmed = value.trim()

  return trimmed === "" ? null : trimmed
}

function toId(value: string): number {
  return Number.parseInt(value.trim(), 10)
}

export function toProductInput(values: ProductFormValues): ProductInput {
  return {
    name: values.name.trim(),
    brandId: toId(values.brandId),
    unitId: toId(values.unitId),
    categoryId: values.categoryId.trim() === "" ? null : toId(values.categoryId),
    description: toNullable(values.description),
    variants: values.variants.map((variant) => ({
      sku: variant.sku.trim(),
      costPrice: variant.costPrice.trim(),
      sellingPrice: variant.sellingPrice.trim(),
      barcode: toNullable(variant.barcode),
    })),
  }
}

export function toProductUpdateInput(
  values: ProductFormValues,
): ProductUpdateInput {
  const input = toProductInput(values)

  return {
    name: input.name,
    brandId: input.brandId,
    unitId: input.unitId,
    categoryId: input.categoryId,
    description: input.description,
  }
}
