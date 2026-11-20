import { api } from "@/lib/api"

export type InvoiceStatus = "draft" | "issued" | "partially_paid" | "paid" | "voided"
export type InvoiceType = "sales" | "purchase"

export interface InvoiceLine {
  productId: string
  description: string
  quantity: number
  unitPrice: number
  taxRateBps: number
  taxAmount: number
  subtotal: number
  total: number
  position: number
}

export interface InvoiceCreditNote {
  number: string | null
  amount: number
  reason: string | null
  issuedAt: string
}

export interface InvoiceAttributes {
  number: string | null
  status: InvoiceStatus
  type: InvoiceType
  orderId: string
  partyId: string
  partyName: string
  currency: string
  issueDate: string | null
  dueDate: string | null
  total: number
  amountPaid: number
  creditedAmount: number
  balance: number
  overpaymentFlagged: boolean
  overdue: boolean
  lines: InvoiceLine[]
  creditNotes: InvoiceCreditNote[]
  createdAt: string
  updatedAt: string
}

export interface Invoice {
  id: string
  type: "invoice"
  attributes: InvoiceAttributes
}

export interface InvoicePagination {
  currentPage: number
  totalPages: number
  pageSize: number
  totalItems: number
  links: Record<string, string>
}

export interface InvoicesPage {
  items: Invoice[]
  pagination: InvoicePagination
}

export interface InvoiceQuery {
  page: number
  pageSize: number
  status?: InvoiceStatus
  type?: InvoiceType
  orderId?: string
  partyId?: string
  search?: string
  overdue?: boolean
}

export interface InvoiceLineInput {
  productId: string
  description: string
  quantity: number
  unitPrice: number
}

export interface CreateInvoiceInput {
  orderId: string
  type: InvoiceType
  partyId: string
  partyName: string
  currency: string
  lines: InvoiceLineInput[]
}

export interface UpdateInvoiceInput {
  partyId: string
  partyName: string
  currency: string
  lines: InvoiceLineInput[]
}

export interface IssueInvoiceInput {
  dueDate?: string
}

export interface VoidInvoiceInput {
  reason: string
}

export interface ApplyPaymentInput {
  paymentId: string
  amount: number
  currency: string
}

export interface IssueCreditNoteInput {
  amount: number
  reason: string
}

interface ApiEnvelope<T> {
  status?: string
  data?: T
  pagination?: InvoicePagination
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

const BASE = "/api/invoices"

export async function getInvoices(query: InvoiceQuery): Promise<InvoicesPage> {
  const params: Record<string, string | number | boolean> = {
    page: query.page,
    pageSize: query.pageSize,
  }

  if (query.status) params.status = query.status
  if (query.type) params.type = query.type
  if (query.orderId) params.orderId = query.orderId
  if (query.partyId) params.partyId = query.partyId
  if (query.search) params.search = query.search
  if (query.overdue !== undefined) params.overdue = query.overdue

  const response = await api.get<ApiEnvelope<Invoice[]>>(BASE, { params })

  const items = unwrap(response.data)

  if (!response.data.pagination) {
    throw new Error("The API returned an unexpected response.")
  }

  return {
    items,
    pagination: response.data.pagination,
  }
}

export async function getInvoice(id: string): Promise<Invoice> {
  const response = await api.get<ApiEnvelope<Invoice>>(`${BASE}/${id}`)

  return unwrap(response.data)
}

export async function createInvoice(input: CreateInvoiceInput): Promise<Invoice> {
  const response = await api.post<ApiEnvelope<Invoice>>(BASE, input)

  return unwrap(response.data)
}

export async function updateInvoice(
  id: string,
  input: UpdateInvoiceInput,
): Promise<Invoice> {
  const response = await api.put<ApiEnvelope<Invoice>>(`${BASE}/${id}`, input)

  return unwrap(response.data)
}

export async function issueInvoice(
  id: string,
  input?: IssueInvoiceInput,
): Promise<Invoice> {
  const response = await api.post<ApiEnvelope<Invoice>>(
    `${BASE}/${id}/issue`,
    input ?? {},
  )

  return unwrap(response.data)
}

export async function voidInvoice(id: string, input: VoidInvoiceInput): Promise<Invoice> {
  const response = await api.post<ApiEnvelope<Invoice>>(
    `${BASE}/${id}/void`,
    input,
  )

  return unwrap(response.data)
}

export async function issueCreditNote(
  id: string,
  input: IssueCreditNoteInput,
): Promise<Invoice> {
  const response = await api.post<ApiEnvelope<Invoice>>(
    `${BASE}/${id}/credit-notes`,
    input,
  )

  return unwrap(response.data)
}

export async function applyPayment(
  id: string,
  input: ApplyPaymentInput,
): Promise<Invoice> {
  const response = await api.post<ApiEnvelope<Invoice>>(
    `${BASE}/${id}/payments`,
    input,
  )

  return unwrap(response.data)
}

export async function getInvoicePdf(id: string): Promise<Blob> {
  const response = await api.get(`${BASE}/${id}/pdf`, {
    responseType: "blob",
  })

  return response.data as Blob
}

export function formatMoney(minorUnits: number, currency = "USD"): string {
  return new Intl.NumberFormat("en-US", {
    style: "currency",
    currency,
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(minorUnits / 100)
}

export default getInvoices
