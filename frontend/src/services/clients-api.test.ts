import { beforeEach, describe, expect, it, vi } from "vitest"
import { api } from "@/lib/api"
import {
  CLIENTS_API_URL,
  createClient,
  deleteClient,
  getClientsTotal,
  updateClient,
  type Client,
  type ClientInput,
} from "./clients-api"

vi.mock("@/lib/api", () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  },
}))

const response = (data: unknown) => ({ data }) as never

const makeClients = (count: number): Client[] =>
  Array.from({ length: count }, (_, index) => ({
    id: index + 1,
    name: `Client ${index + 1}`,
    email: null,
    phone: null,
    address: null,
    createdAt: "2026-01-01T00:00:00.000Z",
    updatedAt: null,
    type: "entreprise",
    cifId: null,
  }))

const input: ClientInput = {
  name: "Ada",
  email: null,
  phone: null,
  address: null,
  type: "entreprise",
  cifId: null,
}

const client: Client = {
  id: 7,
  name: "Ada",
  email: null,
  phone: null,
  address: null,
  createdAt: "2026-01-01T00:00:00.000Z",
  updatedAt: null,
  type: "entreprise",
  cifId: null,
}

const pageCall = (page: number) => [
  "/api/contact",
  { baseURL: CLIENTS_API_URL, params: { page, limit: 100 } },
]

beforeEach(() => {
  vi.clearAllMocks()
})

describe("getClientsTotal", () => {
  it("sums the last partial page", async () => {
    vi.mocked(api.get)
      .mockResolvedValueOnce(response(makeClients(100)))
      .mockResolvedValueOnce(response(makeClients(25)))

    await expect(getClientsTotal()).resolves.toBe(125)

    expect(api.get).toHaveBeenNthCalledWith(1, ...pageCall(1))
    expect(api.get).toHaveBeenNthCalledWith(2, ...pageCall(2))
  })

  it("counts a full page and stops on the empty one", async () => {
    vi.mocked(api.get)
      .mockResolvedValueOnce(response(makeClients(100)))
      .mockResolvedValueOnce(response([]))

    await expect(getClientsTotal()).resolves.toBe(100)

    expect(api.get).toHaveBeenNthCalledWith(1, ...pageCall(1))
    expect(api.get).toHaveBeenNthCalledWith(2, ...pageCall(2))
  })

  it("returns zero when the first page is empty", async () => {
    vi.mocked(api.get).mockResolvedValueOnce(response([]))

    await expect(getClientsTotal()).resolves.toBe(0)

    expect(api.get).toHaveBeenCalledTimes(1)
    expect(api.get).toHaveBeenCalledWith(...pageCall(1))
  })

  it("fails after the safety page limit instead of walking forever", async () => {
    vi.mocked(api.get).mockResolvedValue(response(makeClients(100)))

    await expect(getClientsTotal()).rejects.toThrow("Unable to determine client total.")
    expect(api.get).toHaveBeenCalledTimes(1000)
  })
})

describe("createClient", () => {
  it("posts the input and returns the created client", async () => {
    vi.mocked(api.post).mockResolvedValueOnce(response(client))

    await expect(createClient(input)).resolves.toBe(client)

    expect(api.post).toHaveBeenCalledWith("/api/contact", input, {
      baseURL: CLIENTS_API_URL,
    })
  })
})

describe("updateClient", () => {
  it("puts the input and returns the updated client", async () => {
    vi.mocked(api.put).mockResolvedValueOnce(response(client))

    await expect(updateClient(7, input)).resolves.toBe(client)

    expect(api.put).toHaveBeenCalledWith("/api/contact/7", input, {
      baseURL: CLIENTS_API_URL,
    })
  })
})

describe("deleteClient", () => {
  it("deletes the client and resolves undefined", async () => {
    vi.mocked(api.delete).mockResolvedValueOnce(response(undefined))

    await expect(deleteClient(7)).resolves.toBeUndefined()

    expect(api.delete).toHaveBeenCalledWith("/api/contact/7", {
      baseURL: CLIENTS_API_URL,
    })
  })
})
