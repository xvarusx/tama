import { api } from "@/lib/api";

export type ContactType = "client" | "supplier";

/**
 * Contacts (clients and suppliers) live in the contact service and are
 * exposed by the gateway under `/api/contacts`.
 */
export interface Contact {
  id: string;
  name: string;
  email: string | null;
  phone: string | null;
  address: string | null;
  contactType: ContactType;
  taxNumber: string | null;
  status: string;
  createdAt: string;
  updatedAt: string | null;
  createdBy: string;
  updatedBy: string | null;
}

export interface ContactInput {
  name: string;
  email: string | null;
  phone: string | null;
  address: string | null;
  contactType: ContactType;
  taxNumber: string | null;
}

/** The contact type is fixed once a contact has been created. */
export type ContactUpdateInput = Omit<ContactInput, "contactType">;

export interface Pagination {
  currentPage: number;
  totalPages: number;
  pageSize: number;
  totalItems: number;
  links: {
    first: string;
    prev?: string;
    next?: string;
    last: string;
  };
}

export interface PaginatedContacts {
  data: Contact[];
  pagination: Pagination;
}

export const CONTACT_TYPES = {
  client: "Client",
  supplier: "Supplier",
} as const;

function logRequestError(action: string, error: unknown) {
  console.error(action, error instanceof Error ? error.message : error);
}

/** JSON:API resources keep the id next to `attributes`; the UI model is flat. */
function extractContactFromResource(resource: { id: string; attributes: Omit<Contact, "id"> }): Contact {
  return { ...resource.attributes, id: resource.id };
}

function extractContactsFromResponse(response: { data: Array<{ id: string; attributes: Omit<Contact, "id"> }>; pagination: Pagination }): PaginatedContacts {
  return {
    data: response.data.map(extractContactFromResource),
    pagination: response.pagination,
  };
}

export async function getContacts(
  page: number,
  pageSize: number,
  options?: {
    search?: string;
    type?: ContactType;
    status?: string;
    trustLevel?: string;
  }
): Promise<PaginatedContacts> {
  try {
    const params: Record<string, string | number> = {
      page,
      pageSize,
    };

    if (options?.search) params.search = options.search;
    if (options?.type) params.type = options.type;
    if (options?.status) params.status = options.status;
    if (options?.trustLevel) params.trustLevel = options.trustLevel;

    const response = await api.get<{ status: string; data: Array<{ id: string; attributes: Omit<Contact, "id"> }>; pagination: Pagination }>(
      "/api/contacts/contacts",
      { params }
    );

    if (response.data.status !== "success") {
      throw new Error("Unexpected response status");
    }

    return extractContactsFromResponse(response.data);
  } catch (error) {
    logRequestError("Failed to fetch contacts:", error);
    throw error;
  }
}

export async function getContact(id: string): Promise<Contact> {
  try {
    const response = await api.get<{ status: string; data: { id: string; attributes: Omit<Contact, "id"> } }>(
      `/api/contacts/contacts/${id}`,
    );

    if (response.data.status !== "success") {
      throw new Error("Failed to load contact");
    }

    return extractContactFromResource(response.data.data);
  } catch (error) {
    logRequestError("Failed to load contact:", error);
    throw error;
  }
}

const CONTACTS_PAGE_SIZE = 100;
const MAX_CONTACT_PAGES = 1_000;

export async function getContactsTotal(): Promise<number> {
  let total = 0;

  for (let page = 1; page <= MAX_CONTACT_PAGES; page += 1) {
    const result = await getContacts(page, CONTACTS_PAGE_SIZE);
    total += result.data.length;

    if (result.data.length < CONTACTS_PAGE_SIZE) return total;
  }

  throw new Error("Unable to determine contact total.");
}

export async function createContact(input: ContactInput): Promise<Contact> {
  try {
    const response = await api.post<{ status: string; data: { id: string; attributes: Omit<Contact, "id"> } }>(
      "/api/contacts/contacts",
      input
    );

    if (response.data.status !== "success") {
      throw new Error("Failed to create contact");
    }

    return extractContactFromResource(response.data.data);
  } catch (error) {
    logRequestError("Failed to create contact:", error);
    throw error;
  }
}

export async function updateContact(
  id: string,
  input: ContactUpdateInput,
): Promise<Contact> {
  try {
    const response = await api.put<{ status: string; data: { id: string; attributes: Omit<Contact, "id"> } }>(
      `/api/contacts/contacts/${id}`,
      input
    );

    if (response.data.status !== "success") {
      throw new Error("Failed to update contact");
    }

    return extractContactFromResource(response.data.data);
  } catch (error) {
    logRequestError("Failed to update contact:", error);
    throw error;
  }
}

export async function deleteContact(id: string): Promise<void> {
  try {
    await api.delete(`/api/contacts/contacts/${id}`);
  } catch (error) {
    logRequestError("Failed to delete contact:", error);
    throw error;
  }
}

// Status change functions
export async function deactivateContact(id: string): Promise<Contact> {
  try {
    const response = await api.post<{ status: string; data: { id: string; attributes: Omit<Contact, "id"> } }>(
      `/api/contacts/contacts/${id}/deactivate`
    );

    if (response.data.status !== "success") {
      throw new Error("Failed to deactivate contact");
    }

    return extractContactFromResource(response.data.data);
  } catch (error) {
    logRequestError("Failed to deactivate contact:", error);
    throw error;
  }
}

export async function activateContact(id: string): Promise<Contact> {
  try {
    const response = await api.post<{ status: string; data: { id: string; attributes: Omit<Contact, "id"> } }>(
      `/api/contacts/contacts/${id}/activate`
    );

    if (response.data.status !== "success") {
      throw new Error("Failed to activate contact");
    }

    return extractContactFromResource(response.data.data);
  } catch (error) {
    logRequestError("Failed to activate contact:", error);
    throw error;
  }
}

export async function blacklistContact(id: string, reason: string): Promise<Contact> {
  try {
    const response = await api.post<{ status: string; data: { id: string; attributes: Omit<Contact, "id"> } }>(
      `/api/contacts/contacts/${id}/blacklist`,
      { reason }
    );

    if (response.data.status !== "success") {
      throw new Error("Failed to blacklist contact");
    }

    return extractContactFromResource(response.data.data);
  } catch (error) {
    logRequestError("Failed to blacklist contact:", error);
    throw error;
  }
}

export async function reinstateContact(id: string): Promise<Contact> {
  try {
    const response = await api.post<{ status: string; data: { id: string; attributes: Omit<Contact, "id"> } }>(
      `/api/contacts/contacts/${id}/reinstate`
    );

    if (response.data.status !== "success") {
      throw new Error("Failed to reinstate contact");
    }

    return extractContactFromResource(response.data.data);
  } catch (error) {
    logRequestError("Failed to reinstate contact:", error);
    throw error;
  }
}

export default getContacts;

