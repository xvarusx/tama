import { api } from "@/lib/api"

export type MovementType = "in" | "out" | "adjustment"

export interface InventoryItem {
  id: string
  productId: string
  quantityOnHand: string
  quantityReserved: string
  reorderLevel: string
  availableQuantity: string
  lowStock: boolean
  version: number
  createdAt: string | null
  updatedAt: string | null
  deletedAt: string | null
}

export interface InventoryMovement {
  id: string
  inventoryId: string | null
  productId: string
  type: MovementType
  quantity: string
  quantityAfter: string | null
  quantityDelta: string | null
  reservedDelta: string | null
  reservedAfter: string | null
  referenceType: string | null
  referenceId: string | null
  reason: string | null
  createdAt: string
}

export interface InventoryPagination {
  currentPage: number
  totalPages: number
  pageSize: number
  totalItems: number
  links: Record<string, string>
}

export interface InventoryPage {
  items: InventoryItem[]
  pagination: InventoryPagination
}

export interface InventoryQuery {
  page: number
  pageSize: number
}

export interface CreateInventoryInput {
  productId: string
  quantityOnHand?: number
  reorderLevel?: number
}

export interface CreateInventoryMovementInput {
  type: MovementType
  quantity: string
  referenceType?: string
  referenceId?: string
  reason?: string
}

export interface UpdateInventoryInput {
  reorderLevel: number
  version: number
}

interface ApiEnvelope<T> {
  status?: string
  data?: T
  pagination?: InventoryPagination
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

const BASE = "/api/inventories"

export async function getInventories(query: InventoryQuery): Promise<InventoryPage> {
  const response = await api.get<ApiEnvelope<InventoryItem[]>>(BASE, {
    params: {
      page: query.page,
      limit: query.pageSize,
    },
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

export async function getInventory(id: string): Promise<InventoryItem> {
  const response = await api.get<ApiEnvelope<InventoryItem>>(`${BASE}/${id}`)

  return unwrap(response.data)
}

export async function getInventoryByProduct(productId: string): Promise<InventoryItem> {
  const response = await api.get<ApiEnvelope<InventoryItem>>(
    `${BASE}/product/${productId}`,
  )

  return unwrap(response.data)
}

export async function getLowStock(): Promise<InventoryItem[]> {
  const response = await api.get<ApiEnvelope<InventoryItem[]>>(
    `${BASE}/low-stock`,
  )

  return unwrap(response.data)
}

export async function getOutOfStock(): Promise<InventoryItem[]> {
  const response = await api.get<ApiEnvelope<InventoryItem[]>>(
    `${BASE}/out-of-stock`,
  )

  return unwrap(response.data)
}

export async function createInventory(
  input: CreateInventoryInput,
): Promise<InventoryItem> {
  const response = await api.post<ApiEnvelope<InventoryItem>>(BASE, input)

  return unwrap(response.data)
}

export async function updateInventory(
  id: string,
  input: UpdateInventoryInput,
): Promise<InventoryItem> {
  const response = await api.patch<ApiEnvelope<InventoryItem>>(
    `${BASE}/${id}`,
    input,
  )

  return unwrap(response.data)
}

export async function deleteInventory(id: string): Promise<void> {
  await api.delete(`${BASE}/${id}`)
}

export async function getMovements(
  inventoryId: string,
  page: number,
  limit: number,
): Promise<{ items: InventoryMovement[]; total: number }> {
  const response = await api.get<ApiEnvelope<InventoryMovement[]>>(
    `${BASE}/${inventoryId}/movements`,
    { params: { page, limit } },
  )

  const items = unwrap(response.data)
  const total = response.headers["x-total-count"]
    ? Number(response.headers["x-total-count"])
    : items.length

  return { items, total }
}

export async function createMovement(
  inventoryId: string,
  input: CreateInventoryMovementInput,
): Promise<InventoryMovement> {
  const response = await api.post<ApiEnvelope<InventoryMovement>>(
    `${BASE}/${inventoryId}/movements`,
    input,
  )

  return unwrap(response.data)
}

export default getInventories
