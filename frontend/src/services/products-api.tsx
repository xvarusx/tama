import { api } from "@/lib/api"

export interface ProductBrand {
  id: string
  name: string
  slug?: string
}

export interface ProductUnit {
  id: string
  code: string
  allowDecimal?: boolean
}

export interface ProductCategory {
  id: string
  name: string
  slug?: string
}

export interface ProductVariant {
  id: string
  sku: string
  barcode: string | null
  options: Record<string, string> | null
  sellingPrice: string
  costPrice: string | null
}

export interface ProductMedia {
  id: string
  /** Relative to the product API base: /media/{id}. */
  url: string
  kind: string
  position: number
}

export interface Product {
  id: string
  type: "product"
  attributes: {
    name: string
    description: string | null
    brand: ProductBrand
    unit: ProductUnit
    category: ProductCategory | null
    variants: ProductVariant[]
    media: ProductMedia[]
    createdAt: string | null
    updatedAt: string | null
  }
}

export interface ProductPagination {
  currentPage: number
  totalPages: number
  pageSize: number
  totalItems: number
  links: Record<string, string>
}

export interface ProductPage {
  items: Product[]
  pagination: ProductPagination
}

export interface ProductQuery {
  page: number
  pageSize: number
  search?: string
  categoryId?: string
  brandId?: string
}

export interface VariantInput {
  id?: string
  sku: string
  costPrice: string
  sellingPrice: string
  barcode: string | null
  options?: Record<string, string> | null
}

export interface ProductInput {
  name: string
  brandId: string
  unitId: string
  categoryId: string | null
  description: string | null
  variants: VariantInput[]
}

export type ProductUpdateInput = ProductInput

interface ApiEnvelope<T> {
  status?: string
  data?: T
  pagination?: ProductPagination
  error?: { code?: string; message?: string } | string
}

function envelopeError(error: ApiEnvelope<unknown>["error"]): string {
  if (typeof error === "string") return error
  return error?.message ?? "The API returned an error."
}

function unwrap<T>(envelope: ApiEnvelope<T>): T {
  if (envelope.status === "error") throw new Error(envelopeError(envelope.error))

  if (envelope.data === undefined) {
    throw new Error("The API returned an unexpected response.")
  }

  return envelope.data
}

const BASE = "/api/products"

export async function getProducts(query: ProductQuery): Promise<ProductPage> {
  const params: Record<string, string | number> = {
    page: query.page,
    pageSize: query.pageSize,
  }

  const search = query.search?.trim()
  if (search) params.search = search
  if (query.categoryId) params.categoryId = query.categoryId
  if (query.brandId) params.brandId = query.brandId

  const response = await api.get<ApiEnvelope<Product[]>>(`${BASE}/products`, {
    params,
  })

  const items = unwrap(response.data)

  if (!response.data.pagination) {
    throw new Error("The API returned an unexpected response.")
  }

  return {
    items,
    pagination: response.data.pagination,
  }
}

export async function getProduct(id: string): Promise<Product> {
  const response = await api.get<ApiEnvelope<Product>>(`${BASE}/products/${id}`)

  return unwrap(response.data)
}

export async function createProduct(input: ProductInput): Promise<Product> {
  const response = await api.post<ApiEnvelope<Product>>(`${BASE}/products`, input)

  return unwrap(response.data)
}

export async function updateProduct(
  id: string,
  input: ProductUpdateInput,
): Promise<Product> {
  const response = await api.put<ApiEnvelope<Product>>(
    `${BASE}/products/${id}`,
    input,
  )

  return unwrap(response.data)
}

export async function deleteProduct(id: string): Promise<void> {
  await api.delete(`${BASE}/products/${id}`)
}

export async function getBrands(): Promise<ProductBrand[]> {
  const response = await api.get<ApiEnvelope<ProductBrand[]>>(`${BASE}/brands`)

  return unwrap(response.data)
}

export async function getUnits(): Promise<ProductUnit[]> {
  const response = await api.get<ApiEnvelope<ProductUnit[]>>(`${BASE}/units`)

  return unwrap(response.data)
}

export async function getCategories(): Promise<ProductCategory[]> {
  const response = await api.get<ApiEnvelope<ProductCategory[]>>(
    `${BASE}/categories`,
  )

  return unwrap(response.data)
}

export async function createBrand(name: string): Promise<ProductBrand> {
  const response = await api.post<ApiEnvelope<ProductBrand>>(`${BASE}/brands`, {
    name,
  })

  return unwrap(response.data)
}

export async function createCategory(name: string): Promise<ProductCategory> {
  const response = await api.post<ApiEnvelope<ProductCategory>>(
    `${BASE}/categories`,
    { name },
  )

  return unwrap(response.data)
}

export function productMediaUrl(media: ProductMedia): string {
  return `${BASE}${media.url}`
}

export async function uploadProductMedia(
  productId: string,
  file: File,
): Promise<ProductMedia> {
  const body = new FormData()
  body.append("file", file)

  const response = await api.post<ApiEnvelope<ProductMedia>>(
    `${BASE}/products/${productId}/media`,
    body,
    { headers: { "Content-Type": "multipart/form-data" } },
  )

  return unwrap(response.data)
}

export async function deleteProductMedia(mediaId: string): Promise<void> {
  await api.delete(`${BASE}/media/${mediaId}`)
}

export default getProducts
