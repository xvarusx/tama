import { describe, expect, it } from "vitest"
import {
  createEmptyClientForm,
  toClientFormValues,
  toClientInput,
  validateClientForm,
} from "./client-validation"

const valid = {
  name: "Ada",
  email: "ada@example.com",
  phone: "0612345678",
  address: "1 Main Street",
  type: "entreprise",
  cifId: "CIF-1",
}

describe("validateClientForm", () => {
  it("accepts a complete form", () => {
    expect(validateClientForm(valid)).toEqual({})
  })

  it("requires a non-blank name", () => {
    expect(validateClientForm({ ...valid, name: "   " })).toEqual({
      name: "Name is required.",
    })
  })

  it("requires a type", () => {
    expect(validateClientForm({ ...valid, type: "" })).toEqual({
      type: "Type is required.",
    })
  })

  it("allows an empty email", () => {
    expect(validateClientForm({ ...valid, email: "" })).toEqual({})
  })

  it("rejects an invalid email", () => {
    expect(validateClientForm({ ...valid, email: "ada@example" })).toEqual({
      email: "Enter a valid email address.",
    })
  })
})

describe("toClientInput", () => {
  it("trims text and turns blanks into null", () => {
    expect(
      toClientInput({
        ...valid,
        name: "  Ada  ",
        email: " ",
        phone: "",
        address: "   ",
        cifId: " ",
      }),
    ).toEqual({
      name: "Ada",
      email: null,
      phone: null,
      address: null,
      type: "entreprise",
      cifId: null,
    })
  })
})

describe("client form mapping", () => {
  it("starts with an entreprise and blank fields", () => {
    expect(createEmptyClientForm()).toEqual({
      name: "",
      email: "",
      phone: "",
      address: "",
      type: "entreprise",
      cifId: "",
    })
  })

  it("maps null API values to empty strings", () => {
    expect(
      toClientFormValues({
        name: "Ada",
        email: null,
        phone: null,
        address: null,
        type: "entreprise",
        cifId: null,
      }),
    ).toEqual({
      name: "Ada",
      email: "",
      phone: "",
      address: "",
      type: "entreprise",
      cifId: "",
    })
  })
})
