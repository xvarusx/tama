import { api } from "@/lib/api";

export type ContactType = "client" | "supplier";

/**
 * Contacts (clients and suppliers) live in the contact service and are
 * exposed by the gateway under `/api/contact`.
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
  trustLevel: string;
  createdAt: string;
  updatedAt: string | null;
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

export const CONTACT_TYPES = {
  client: "Client",
  supplier: "Supplier",
} as const;

function logRequestError(action: string, error: unknown) {
  console.error(action, error instanceof Error ? error.message : error);
}

export default async function getContacts(
  page: number,
  limit: number,
): Promise<Contact[]> {
  try {
    const response = await api.get<Contact[]>("/api/contact", {
      params: { page, limit },
    });

    return response.data;
  } catch (error) {
    logRequestError("Failed to fetch contacts:", error);
    throw error;
  }
}

const CONTACTS_PAGE_SIZE = 100;
const MAX_CONTACT_PAGES = 1_000;

export async function getContactsTotal(): Promise<number> {
  let total = 0;

  for (let page = 1; page <= MAX_CONTACT_PAGES; page += 1) {
    const contacts = await getContacts(page, CONTACTS_PAGE_SIZE);
    total += contacts.length;

    if (contacts.length < CONTACTS_PAGE_SIZE) return total;
  }

  throw new Error("Unable to determine contact total.");
}

export async function createContact(input: ContactInput): Promise<Contact> {
  try {
    const response = await api.post<Contact>("/api/contact", input);

    return response.data;
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
    const response = await api.put<Contact>(`/api/contact/${id}`, input);

    return response.data;
  } catch (error) {
    logRequestError("Failed to update contact:", error);
    throw error;
  }
}

export async function deleteContact(id: string): Promise<void> {
  try {
    await api.delete(`/api/contact/${id}`);
  } catch (error) {
    logRequestError("Failed to delete contact:", error);
    throw error;
  }
}
