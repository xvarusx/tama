import { api } from "@/lib/api"

export type PaymentStatus = "PENDING" | "COMPLETED" | "FAILED" | "CANCELED" | "REVERSED"
export type PaymentMethod = "CASH" | "CHEQUE" | "BANK_TRANSFER" | "OTHER"
export type PaymentDirection = "INCOMING" | "OUTGOING"
export type InstallmentStatus = "UNPAID" | "PARTIALLY_PAID" | "PAID" | "CANCELED"
export type ReminderKind = "UPCOMING_DUE" | "OVERDUE"

export interface PaymentAllocation {
  id: string
  type: "payment-allocation"
  attributes: {
    paymentId: string
    installmentId: string
    invoiceId: string
    amount: number
  }
}

export interface Payment {
  id: string
  type: "payment"
  attributes: {
    number: string
    direction: PaymentDirection
    method: PaymentMethod
    status: PaymentStatus
    partyId: string
    partyName: string
    currency: string
    amount: number
    paidAt: string | null
    reference: string | null
    version: number
    allocations: PaymentAllocation[]
  }
}

export interface PaymentPagination {
  currentPage: number
  totalPages: number
  pageSize: number
  totalItems: number
  links: Record<string, string>
}

export interface PaymentsPage {
  items: Payment[]
  pagination: PaymentPagination
}

export interface PaymentQuery {
  page: number
  pageSize: number
  status?: PaymentStatus
  method?: PaymentMethod
  partyId?: string
  search?: string
}

export interface CreatePaymentInput {
  number: string
  direction: PaymentDirection
  method: PaymentMethod
  partyId: string
  partyName: string
  currency: string
  amount: number
  reference?: string
}

export interface PaymentPlanInstallment {
  id: string
  type: "installment"
  attributes: {
    sequence: number
    dueDate: string
    amount: number
    paidAmount: number
    status: InstallmentStatus
    remaining: number
  }
}

/**
 * Installments embedded in a payment plan are serialized flat (id + fields),
 * unlike the standalone /installments resource collection.
 */
export interface PaymentPlanInstallmentEntry {
  id: string
  sequence: number
  dueDate: string
  amount: number
  paidAmount: number
  status: InstallmentStatus
  remaining: number
}

export interface PaymentPlan {
  id: string
  type: "payment-plan"
  attributes: {
    invoiceId: string
    totalAmount: number
    currency: string
    status: string
    version: number
    installments: PaymentPlanInstallmentEntry[]
  }
}

export interface PaymentPlanPagination {
  currentPage: number
  totalPages: number
  pageSize: number
  totalItems: number
  links: Record<string, string>
}

export interface PaymentPlansPage {
  items: PaymentPlan[]
  pagination: PaymentPlanPagination
}

export interface PaymentPlanQuery {
  page: number
  pageSize: number
  invoiceId?: string
}

export interface CreatePaymentPlanInput {
  invoiceId: string
  totalAmount: number
  currency: string
  installments: Array<{ dueDate: string; amount: number }>
}

export interface InstallmentQuery {
  page: number
  pageSize: number
  status?: InstallmentStatus
  paymentPlanId?: string
  overdueOnly?: boolean
}

export interface InstallmentsPage {
  items: PaymentPlanInstallment[]
  pagination: PaymentPlanPagination
}

export interface Reminder {
  id: string
  type: "reminder"
  attributes: {
    installmentId: string
    kind: ReminderKind
    scheduledFor: string
    status: string
    sentAt: string | null
  }
}

export interface ReminderPagination {
  currentPage: number
  totalPages: number
  pageSize: number
  totalItems: number
  links: Record<string, string>
}

export interface RemindersPage {
  items: Reminder[]
  pagination: ReminderPagination
}

export interface ReminderQuery {
  page: number
  pageSize: number
  kind?: ReminderKind
}

export interface Setting {
  key: string
  attributes: {
    value: string
    type: string
    description: string | null
  }
}

export interface PaymentAllocationsPage {
  items: PaymentAllocation[]
}

export interface CreateAllocationInput {
  paymentId: string
  installmentId: string
  amount: number
}

interface ApiEnvelope<T> {
  status?: string
  data?: T
  pagination?: PaymentPagination | PaymentPlanPagination | ReminderPagination
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

const PAYMENTS_BASE = "/api/payments"
const PAYMENT_PLANS_BASE = "/api/payment-plans"
const INSTALLMENTS_BASE = "/api/installments"
const ALLOCATIONS_BASE = "/api/allocations"
const REMINDERS_BASE = "/api/reminders"
const SETTINGS_BASE = "/api/settings"

export async function getPayments(query: PaymentQuery): Promise<PaymentsPage> {
  const params: Record<string, string | number> = {
    page: query.page,
    pageSize: query.pageSize,
  }

  if (query.status) params.status = query.status
  if (query.method) params.method = query.method
  if (query.partyId) params.partyId = query.partyId
  if (query.search) params.search = query.search

  const response = await api.get<ApiEnvelope<Payment[]>>(PAYMENTS_BASE, { params })

  const items = unwrap(response.data)

  if (!response.data.pagination) {
    throw new Error("The API returned an unexpected response.")
  }

  return {
    items,
    pagination: response.data.pagination,
  }
}

export async function getPayment(id: string): Promise<Payment> {
  const response = await api.get<ApiEnvelope<Payment>>(`${PAYMENTS_BASE}/${id}`)

  return unwrap(response.data)
}

export async function createPayment(input: CreatePaymentInput): Promise<Payment> {
  const response = await api.post<ApiEnvelope<Payment>>(PAYMENTS_BASE, input)

  return unwrap(response.data)
}

export async function recordPayment(id: string): Promise<Payment> {
  const response = await api.post<ApiEnvelope<Payment>>(
    `${PAYMENTS_BASE}/${id}/record`,
  )

  return unwrap(response.data)
}

export async function failPayment(id: string, reason: string): Promise<Payment> {
  const response = await api.post<ApiEnvelope<Payment>>(
    `${PAYMENTS_BASE}/${id}/fail`,
    { paymentId: id, reason },
  )

  return unwrap(response.data)
}

export async function reversePayment(id: string, reason: string): Promise<Payment> {
  const response = await api.post<ApiEnvelope<Payment>>(
    `${PAYMENTS_BASE}/${id}/reverse`,
    { paymentId: id, reason },
  )

  return unwrap(response.data)
}

export async function cancelPayment(id: string): Promise<Payment> {
  const response = await api.delete<ApiEnvelope<Payment>>(
    `${PAYMENTS_BASE}/${id}/cancel`,
  )

  return unwrap(response.data)
}

export async function getPaymentPlans(
  query: PaymentPlanQuery,
): Promise<PaymentPlansPage> {
  const params: Record<string, string | number> = {
    page: query.page,
    pageSize: query.pageSize,
  }

  if (query.invoiceId) params.invoiceId = query.invoiceId

  const response = await api.get<ApiEnvelope<PaymentPlan[]>>(
    PAYMENT_PLANS_BASE,
    { params },
  )

  const items = unwrap(response.data)

  if (!response.data.pagination) {
    throw new Error("The API returned an unexpected response.")
  }

  return {
    items,
    pagination: response.data.pagination,
  }
}

export async function getPaymentPlan(id: string): Promise<PaymentPlan> {
  const response = await api.get<ApiEnvelope<PaymentPlan>>(
    `${PAYMENT_PLANS_BASE}/${id}`,
  )

  return unwrap(response.data)
}

export async function createPaymentPlan(
  input: CreatePaymentPlanInput,
): Promise<PaymentPlan> {
  const response = await api.post<ApiEnvelope<PaymentPlan>>(
    PAYMENT_PLANS_BASE,
    input,
  )

  return unwrap(response.data)
}

export async function cancelPaymentPlan(id: string): Promise<PaymentPlan> {
  const response = await api.delete<ApiEnvelope<PaymentPlan>>(
    `${PAYMENT_PLANS_BASE}/${id}`,
  )

  return unwrap(response.data)
}

export async function getInstallments(
  query: InstallmentQuery,
): Promise<InstallmentsPage> {
  const params: Record<string, string | number | boolean> = {
    page: query.page,
    pageSize: query.pageSize,
  }

  if (query.status) params.status = query.status
  if (query.paymentPlanId) params.paymentPlanId = query.paymentPlanId
  if (query.overdueOnly !== undefined) params.overdueOnly = query.overdueOnly

  const response = await api.get<ApiEnvelope<PaymentPlanInstallment[]>>(
    INSTALLMENTS_BASE,
    { params },
  )

  const items = unwrap(response.data)

  if (!response.data.pagination) {
    throw new Error("The API returned an unexpected response.")
  }

  return {
    items,
    pagination: response.data.pagination,
  }
}

export async function getInstallment(id: string): Promise<PaymentPlanInstallment> {
  const response = await api.get<ApiEnvelope<PaymentPlanInstallment>>(
    `${INSTALLMENTS_BASE}/${id}`,
  )

  return unwrap(response.data)
}

export async function getAllocations(): Promise<PaymentAllocationsPage> {
  const response = await api.get<ApiEnvelope<PaymentAllocation[]>>(
    ALLOCATIONS_BASE,
  )

  return {
    items: unwrap(response.data),
  }
}

export async function getAllocation(id: string): Promise<PaymentAllocation> {
  const response = await api.get<ApiEnvelope<PaymentAllocation>>(
    `${ALLOCATIONS_BASE}/${id}`,
  )

  return unwrap(response.data)
}

export async function createAllocation(
  input: CreateAllocationInput,
): Promise<PaymentAllocation> {
  const response = await api.post<ApiEnvelope<PaymentAllocation>>(
    ALLOCATIONS_BASE,
    input,
  )

  return unwrap(response.data)
}

export async function getReminders(query: ReminderQuery): Promise<RemindersPage> {
  const params: Record<string, string | number> = {
    page: query.page,
    pageSize: query.pageSize,
  }

  if (query.kind) params.kind = query.kind

  const response = await api.get<ApiEnvelope<Reminder[]>>(REMINDERS_BASE, {
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

export async function getReminder(id: string): Promise<Reminder> {
  const response = await api.get<ApiEnvelope<Reminder>>(`${REMINDERS_BASE}/${id}`)

  return unwrap(response.data)
}

export async function getSettings(): Promise<Setting[]> {
  const response = await api.get<ApiEnvelope<Setting[]>>(SETTINGS_BASE)

  return unwrap(response.data)
}

export async function upsertSetting(
  key: string,
  value: string,
  description?: string,
): Promise<Setting> {
  const response = await api.post<ApiEnvelope<Setting>>(SETTINGS_BASE, {
    key,
    value,
    description,
  })

  return unwrap(response.data)
}

export function formatMoney(
  minorUnits: number,
  currency = "USD",
): string {
  return new Intl.NumberFormat("en-US", {
    style: "currency",
    currency,
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(minorUnits / 100)
}

export const PAYMENT_STATUS_LABELS: Record<PaymentStatus, string> = {
  PENDING: "Pending",
  COMPLETED: "Completed",
  FAILED: "Failed",
  CANCELED: "Canceled",
  REVERSED: "Reversed",
}

export const PAYMENT_STATUS_VARIANTS: Record<PaymentStatus, string> = {
  PENDING: "warning",
  COMPLETED: "success",
  FAILED: "destructive",
  CANCELED: "secondary",
  REVERSED: "secondary",
}

export const PAYMENT_METHOD_LABELS: Record<PaymentMethod, string> = {
  CASH: "Cash",
  CHEQUE: "Cheque",
  BANK_TRANSFER: "Bank transfer",
  OTHER: "Other",
}

export const PAYMENT_DIRECTION_LABELS: Record<PaymentDirection, string> = {
  INCOMING: "Incoming",
  OUTGOING: "Outgoing",
}

export const INSTALLMENT_STATUS_LABELS: Record<InstallmentStatus, string> = {
  UNPAID: "Unpaid",
  PARTIALLY_PAID: "Partially paid",
  PAID: "Paid",
  CANCELED: "Canceled",
}

export const INSTALLMENT_STATUS_VARIANTS: Record<InstallmentStatus, string> = {
  UNPAID: "secondary",
  PARTIALLY_PAID: "warning",
  PAID: "success",
  CANCELED: "secondary",
}

export default getPayments
