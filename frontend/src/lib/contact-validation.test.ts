import { describe, expect, it } from "vitest"
import type { ContactType } from "@/services/contacts-api"
import {
  createEmptyContactForm,
  toContactFormValues,
  toContactInput,
  toContactUpdateInput,
  validateContactForm,
} from "./contact-validation"

const valid = {
  name: "Ada",
  email: "ada@example.com",
  phone: "0612345678",
  address: "1 Main Street",
  contactType: "client" as const,
  taxNumber: "CIF-1",
}

describe("validateContactForm", () => {
  it("accepts a complete form", () => {
    expect(validateContactForm(valid)).toEqual({})
  })

  it("requires a non-blank name", () => {
    expect(validateContactForm({ ...valid, name: "   " })).toEqual({
      name: "Name is required.",
    })
  })

  it("requires a type", () => {
    expect(
      validateContactForm({ ...valid, contactType: "" as ContactType }),
    ).toEqual({
      contactType: "Type is required.",
    })
  })

  it("allows an empty email", () => {
    expect(validateContactForm({ ...valid, email: "" })).toEqual({})
  })

  it("rejects an invalid email", () => {
    expect(validateContactForm({ ...valid, email: "ada@example" })).toEqual({
      email: "Enter a valid email address.",
    })
  })
})

describe("toContactInput", () => {
  it("trims text and turns blanks into null", () => {
    expect(
      toContactInput({
        ...valid,
        name: "  Ada  ",
        email: " ",
        phone: "",
        address: "   ",
        taxNumber: " ",
      }),
    ).toEqual({
      name: "Ada",
      email: null,
      phone: null,
      address: null,
      contactType: "client",
      taxNumber: null,
    })
  })
})

describe("toContactUpdateInput", () => {
  it("omits the immutable contact type", () => {
    expect(toContactUpdateInput(valid)).toEqual({
      name: "Ada",
      email: "ada@example.com",
      phone: "0612345678",
      address: "1 Main Street",
      taxNumber: "CIF-1",
    })
  })
})

describe("contact form mapping", () => {
  it("starts as a client with blank fields", () => {
    expect(createEmptyContactForm()).toEqual({
      name: "",
      email: "",
      phone: "",
      address: "",
      contactType: "client",
      taxNumber: "",
    })
  })

  it("maps null API values to empty strings", () => {
    expect(
      toContactFormValues({
        name: "Ada",
        email: null,
        phone: null,
        address: null,
        contactType: "supplier",
        taxNumber: null,
      }),
    ).toEqual({
      name: "Ada",
      email: "",
      phone: "",
      address: "",
      contactType: "supplier",
      taxNumber: "",
    })
  })
})
