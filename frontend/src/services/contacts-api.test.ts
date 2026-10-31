import { beforeEach, describe, expect, it, vi } from "vitest"
import { api } from "@/lib/api"
import {
  createContact,
  deleteContact,
  getContactsTotal,
  updateContact,
  type Contact,
  type ContactInput,
  type ContactUpdateInput,
} from "./contacts-api"

vi.mock("@/lib/api", () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  },
}))

const response = (data: unknown) => ({ data }) as never

const makeContacts = (count: number): Contact[] =>
  Array.from({ length: count }, (_, index) => ({
    id: `00000000-0000-7000-8000-${String(index + 1).padStart(12, "0")}`,
    name: `Contact ${index + 1}`,
    email: null,
    phone: null,
    address: null,
    contactType: "client",
    taxNumber: null,
    status: "active",
    trustLevel: "unrated",
    createdAt: "2026-01-01T00:00:00.000Z",
    updatedAt: null,
  }))

const input: ContactInput = {
  name: "Ada",
  email: null,
  phone: null,
  address: null,
  contactType: "client",
  taxNumber: null,
}

const updateInput: ContactUpdateInput = {
  name: "Ada",
  email: null,
  phone: null,
  address: null,
  taxNumber: null,
}

const contact: Contact = {
  id: "00000000-0000-7000-8000-000000000007",
  name: "Ada",
  email: null,
  phone: null,
  address: null,
  contactType: "client",
  taxNumber: null,
  status: "active",
  trustLevel: "unrated",
  createdAt: "2026-01-01T00:00:00.000Z",
  updatedAt: null,
}

const pageCall = (page: number) => [
  "/api/contact",
  { params: { page, limit: 100 } },
]

beforeEach(() => {
  vi.clearAllMocks()
})

describe("getContactsTotal", () => {
  it("sums the last partial page", async () => {
    vi.mocked(api.get)
      .mockResolvedValueOnce(response(makeContacts(100)))
      .mockResolvedValueOnce(response(makeContacts(25)))

    await expect(getContactsTotal()).resolves.toBe(125)

    expect(api.get).toHaveBeenNthCalledWith(1, ...pageCall(1))
    expect(api.get).toHaveBeenNthCalledWith(2, ...pageCall(2))
  })

  it("counts a full page and stops on the empty one", async () => {
    vi.mocked(api.get)
      .mockResolvedValueOnce(response(makeContacts(100)))
      .mockResolvedValueOnce(response([]))

    await expect(getContactsTotal()).resolves.toBe(100)

    expect(api.get).toHaveBeenNthCalledWith(1, ...pageCall(1))
    expect(api.get).toHaveBeenNthCalledWith(2, ...pageCall(2))
  })

  it("returns zero when the first page is empty", async () => {
    vi.mocked(api.get).mockResolvedValueOnce(response([]))

    await expect(getContactsTotal()).resolves.toBe(0)

    expect(api.get).toHaveBeenCalledTimes(1)
    expect(api.get).toHaveBeenCalledWith(...pageCall(1))
  })

  it("fails after the safety page limit instead of walking forever", async () => {
    vi.mocked(api.get).mockResolvedValue(response(makeContacts(100)))

    await expect(getContactsTotal()).rejects.toThrow(
      "Unable to determine contact total.",
    )
    expect(api.get).toHaveBeenCalledTimes(1000)
  })
})

describe("createContact", () => {
  it("posts the input and returns the created contact", async () => {
    vi.mocked(api.post).mockResolvedValueOnce(response(contact))

    await expect(createContact(input)).resolves.toBe(contact)

    expect(api.post).toHaveBeenCalledWith("/api/contact", input)
  })
})

describe("updateContact", () => {
  it("puts the editable fields and returns the updated contact", async () => {
    vi.mocked(api.put).mockResolvedValueOnce(response(contact))

    await expect(updateContact(contact.id, updateInput)).resolves.toBe(contact)

    expect(api.put).toHaveBeenCalledWith(
      `/api/contact/${contact.id}`,
      updateInput,
    )
  })
})

describe("deleteContact", () => {
  it("deletes the contact and resolves undefined", async () => {
    vi.mocked(api.delete).mockResolvedValueOnce(response(undefined))

    await expect(deleteContact(contact.id)).resolves.toBeUndefined()

    expect(api.delete).toHaveBeenCalledWith(`/api/contact/${contact.id}`)
  })
})
