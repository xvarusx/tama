import type { Product, ProductInput, ProductStatus } from "@/services/products-api"

export interface ProductFormValues {
  name: string
  sku: string
  brand: string
  unit: string
  sellingPrice: string
  costPrice: string
  imageUrl: string
  description: string
  status: ProductStatus
}

export type ProductFormErrors = Partial<Record<keyof ProductFormValues, string>>

const WHOLE_NUMBER = /^\d+$/
const ALLOWED_STATUSES: ProductStatus[] = ["active", "archive"]

export function createEmptyProductForm(): ProductFormValues {
  return {
    name: "",
    sku: "",
    brand: "",
    unit: "",
    sellingPrice: "",
    costPrice: "",
    imageUrl: "",
    description: "",
    status: "active",
  }
}

export function toProductFormValues(product: Product): ProductFormValues {
  return {
    name: product.name,
    sku: product.sku,
    brand: product.brand ?? "",
    unit: product.unit,
    sellingPrice: product.sellingPrice,
    costPrice: product.costPrice,
    imageUrl: product.imageUrl ?? "",
    description: product.description ?? "",
    status: product.status,
  }
}

function isAllowedImageUrl(value: string): boolean {
  try {
    const url = new URL(value)

    return url.protocol === "https:" || url.protocol === "ftp:"
  } catch {
    return false
  }
}

export function validateProductForm(
  values: ProductFormValues,
): ProductFormErrors {
  const errors: ProductFormErrors = {}

  if (!values.name.trim()) errors.name = "Name is required."
  if (!values.sku.trim()) errors.sku = "SKU is required."
  if (!values.unit.trim()) errors.unit = "Unit is required."

  if (!values.sellingPrice.trim()) {
    errors.sellingPrice = "Selling price is required."
  } else if (!WHOLE_NUMBER.test(values.sellingPrice.trim())) {
    errors.sellingPrice = "Enter a whole number."
  }

  if (!values.costPrice.trim()) {
    errors.costPrice = "Cost price is required."
  } else if (!WHOLE_NUMBER.test(values.costPrice.trim())) {
    errors.costPrice = "Enter a whole number."
  }

  const imageUrl = values.imageUrl.trim()

  if (imageUrl && !isAllowedImageUrl(imageUrl)) {
    errors.imageUrl = "Use an https:// or ftp:// URL."
  }

  if (!ALLOWED_STATUSES.includes(values.status)) {
    errors.status = "Status is invalid."
  }

  return errors
}

function toNullable(value: string): string | null {
  const trimmed = value.trim()

  return trimmed === "" ? null : trimmed
}

export function toProductInput(values: ProductFormValues): ProductInput {
  return {
    name: values.name.trim(),
    sku: values.sku.trim(),
    brand: toNullable(values.brand),
    unit: values.unit.trim(),
    sellingPrice: values.sellingPrice.trim(),
    costPrice: values.costPrice.trim(),
    imageUrl: toNullable(values.imageUrl),
    description: toNullable(values.description),
    status: values.status,
  }
}
