import { api } from "@/lib/api";

export const CLIENTS_API_URL =
  import.meta.env.VITE_CLIENTS_API_URL ?? "";

export interface Client {
  id: number;
  name: string;
  email: string | null;
  phone: string | null;
  address: string | null;
  createdAt: string;
  updatedAt: string | null;
  type: string;
  cifId: string | null;
}

export interface ClientInput {
  name: string;
  email: string | null;
  phone: string | null;
  address: string | null;
  type: string;
  cifId: string | null;
}

export const CLIENT_TYPES = {
  entreprise: "Entreprise",
  particulier: "Particulier",
} as const;

function logRequestError(action: string, error: unknown) {
  console.error(action, error instanceof Error ? error.message : error);
}

export default async function getClients(
  page: number,
  limit: number,
): Promise<Client[]> {
  try {
    const response = await api.get<Client[]>("/api/contact", {
      baseURL: CLIENTS_API_URL,
      params: { page, limit },
    });

    return response.data;
  } catch (error) {
    logRequestError("Failed to fetch clients:", error);
    throw error;
  }
}

const CLIENTS_PAGE_SIZE = 100;
const MAX_CLIENT_PAGES = 1_000;

export async function getClientsTotal(): Promise<number> {
  let total = 0;

  for (let page = 1; page <= MAX_CLIENT_PAGES; page += 1) {
    const clients = await getClients(page, CLIENTS_PAGE_SIZE);
    total += clients.length;

    if (clients.length < CLIENTS_PAGE_SIZE) return total;
  }

  throw new Error("Unable to determine client total.");
}

export async function createClient(input: ClientInput): Promise<Client> {
  try {
    const response = await api.post<Client>("/api/contact", input, {
      baseURL: CLIENTS_API_URL,
    });

    return response.data;
  } catch (error) {
    logRequestError("Failed to create client:", error);
    throw error;
  }
}

export async function updateClient(
  id: number,
  input: ClientInput,
): Promise<Client> {
  try {
    const response = await api.put<Client>(`/api/contact/${id}`, input, {
      baseURL: CLIENTS_API_URL,
    });

    return response.data;
  } catch (error) {
    logRequestError("Failed to update client:", error);
    throw error;
  }
}

export async function deleteClient(id: number): Promise<void> {
  try {
    await api.delete(`/api/contact/${id}`, { baseURL: CLIENTS_API_URL });
  } catch (error) {
    logRequestError("Failed to delete client:", error);
    throw error;
  }
}
