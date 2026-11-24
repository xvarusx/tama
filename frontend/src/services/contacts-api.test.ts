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

const makeContact = (index: number): Contact => ({
  id: `00000000-0000-7000-8000-${String(index + 1).padStart(12, "0")}`,
  name: `Contact ${index + 1}`,
  email: null,
  phone: null,
  address: null,
  contactType: "client",
  taxNumber: null,
  status: "active",
  createdAt: "2026-01-01T00:00:00.000Z",
  updatedAt: null,
  createdBy: `00000000-0000-7000-8000-${String(index + 100).padStart(12, "0")}`,
  updatedBy: null,
})

const makeContacts = (count: number): Contact[] =>
  Array.from({ length: count }, (_, index) => makeContact(index + 1))

const contactEnvelope = (items: Contact[], page = 1, pageSize = 100) => ({
  data: {
    status: "success",
    data: items.map(({ id, ...attributes }) => ({ id, attributes })),
    pagination: {
      currentPage: page,
      totalPages: Math.ceil(items.length / pageSize) || 1,
      pageSize,
      totalItems: items.length,
      links: {},
    },
  },
})

const singleResource = ({ id, ...attributes }: Contact) => ({ id, attributes })

const singleEnvelope = (contact: Contact) => ({
  data: {
    status: "success",
    data: singleResource(contact),
  },
})

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

const contact: Contact = makeContact(7)

const pageCall = (page: number) => [
  "/api/contacts/contacts",
  { params: { page, pageSize: 100 } },
]

beforeEach(() => {
  vi.clearAllMocks()
})

describe("getContactsTotal", () => {
  it("sums the last partial page", async () => {
    vi.mocked(api.get)
      .mockResolvedValueOnce(contactEnvelope(makeContacts(100)))
      .mockResolvedValueOnce(contactEnvelope(makeContacts(25)))

    await expect(getContactsTotal()).resolves.toBe(125)

    expect(api.get).toHaveBeenNthCalledWith(1, ...pageCall(1))
    expect(api.get).toHaveBeenNthCalledWith(2, ...pageCall(2))
  })

  it("counts a full page and stops on the empty one", async () => {
    vi.mocked(api.get)
      .mockResolvedValueOnce(contactEnvelope(makeContacts(100)))
      .mockResolvedValueOnce(contactEnvelope([]))

    await expect(getContactsTotal()).resolves.toBe(100)

    expect(api.get).toHaveBeenNthCalledWith(1, ...pageCall(1))
    expect(api.get).toHaveBeenNthCalledWith(2, ...pageCall(2))
  })

  it("returns zero when the first page is empty", async () => {
    vi.mocked(api.get).mockResolvedValueOnce(contactEnvelope([]))

    await expect(getContactsTotal()).resolves.toBe(0)

    expect(api.get).toHaveBeenCalledTimes(1)
    expect(api.get).toHaveBeenCalledWith(...pageCall(1))
  })

  it("fails after the safety page limit instead of walking forever", async () => {
    vi.mocked(api.get).mockResolvedValue(contactEnvelope(makeContacts(100)))

    await expect(getContactsTotal()).rejects.toThrow(
      "Unable to determine contact total.",
    )
    expect(api.get).toHaveBeenCalledTimes(1000)
  })
})

describe("createContact", () => {
  it("posts the input and returns the created contact", async () => {
    vi.mocked(api.post).mockResolvedValueOnce(singleEnvelope(contact))

    await expect(createContact(input)).resolves.toEqual(contact)

    expect(api.post).toHaveBeenCalledWith("/api/contacts/contacts", input)
  })
})

describe("updateContact", () => {
  it("puts the editable fields and returns the updated contact", async () => {
    vi.mocked(api.put).mockResolvedValueOnce(singleEnvelope(contact))

    await expect(updateContact(contact.id, updateInput)).resolves.toEqual(contact)

    expect(api.put).toHaveBeenCalledWith(
      `/api/contacts/contacts/${contact.id}`,
      updateInput,
    )
  })
})

describe("deleteContact", () => {
  it("deletes the contact and resolves undefined", async () => {
    vi.mocked(api.delete).mockResolvedValueOnce({ data: undefined })

    await expect(deleteContact(contact.id)).resolves.toBeUndefined()

    expect(api.delete).toHaveBeenCalledWith(
      `/api/contacts/contacts/${contact.id}`
    )
  })
})
