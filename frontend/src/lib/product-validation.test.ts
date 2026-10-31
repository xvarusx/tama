import { describe, expect, it } from "vitest"
import type { Product } from "@/services/products-api"
import {
  createEmptyProductForm,
  toProductFormValues,
  toProductInput,
  validateProductForm,
  type ProductFormValues,
} from "./product-validation"

const validValues = (
  overrides: Partial<ProductFormValues> = {},
): ProductFormValues => ({
  name: "Product",
  sku: "SKU-1",
  brand: "Brand",
  unit: "kg",
  sellingPrice: "12",
  costPrice: "10",
  imageUrl: "",
  description: "",
  status: "active",
  ...overrides,
})

const product: Product = {
  id: 7,
  sku: "SKU-7",
  name: "Ada",
  description: null,
  brand: null,
  unit: "kg",
  sellingPrice: "12",
  costPrice: "10",
  imageUrl: null,
  status: "archive",
  createdAt: "2026-01-01T00:00:00.000Z",
  updatedAt: "2026-01-01T00:00:00.000Z",
  category: null,
}

describe("validateProductForm", () => {
  it("requires the core fields on an empty form", () => {
    const errors = validateProductForm(createEmptyProductForm())

    expect(errors.name).toBe("Name is required.")
    expect(errors.sku).toBe("SKU is required.")
    expect(errors.unit).toBe("Unit is required.")
    expect(errors.sellingPrice).toBe("Selling price is required.")
    expect(errors.costPrice).toBe("Cost price is required.")
    expect(errors.status).toBeUndefined()
  })

  it("rejects a decimal selling price", () => {
    expect(
      validateProductForm(validValues({ sellingPrice: "12.5" })).sellingPrice,
    ).toBe("Enter a whole number.")
  })

  it("rejects a negative selling price", () => {
    expect(
      validateProductForm(validValues({ sellingPrice: "-1" })).sellingPrice,
    ).toBe("Enter a whole number.")
  })

  it("rejects a non-numeric cost price", () => {
    expect(
      validateProductForm(validValues({ costPrice: "abc" })).costPrice,
    ).toBe("Enter a whole number.")
  })

  it("rejects an http image URL", () => {
    expect(
      validateProductForm(
        validValues({ imageUrl: "http://example.com/a.png" }),
      ).imageUrl,
    ).toBe("Use an https:// or ftp:// URL.")
  })

  it("accepts an https image URL", () => {
    expect(
      validateProductForm(
        validValues({ imageUrl: "https://example.com/a.png" }),
      ).imageUrl,
    ).toBeUndefined()
  })

  it("rejects a malformed image URL", () => {
    expect(
      validateProductForm(validValues({ imageUrl: "not a url" })).imageUrl,
    ).toBe("Use an https:// or ftp:// URL.")
  })

  it("rejects an unknown status", () => {
    expect(
      validateProductForm(
        validValues({ status: "draft" as ProductFormValues["status"] }),
      ).status,
    ).toBe("Status is invalid.")
  })

  it("accepts a fully valid form", () => {
    expect(validateProductForm(validValues())).toEqual({})
  })
})

describe("toProductInput", () => {
  it("trims text and turns empty optional fields into null", () => {
    expect(
      toProductInput(
        validValues({
          name: "  Ada  ",
          brand: "  ",
          imageUrl: "",
          description: "  ",
        }),
      ),
    ).toEqual({
      name: "Ada",
      sku: "SKU-1",
      brand: null,
      unit: "kg",
      sellingPrice: "12",
      costPrice: "10",
      imageUrl: null,
      description: null,
      status: "active",
    })
  })
})

describe("toProductFormValues", () => {
  it("turns null fields into empty strings and keeps the status", () => {
    expect(toProductFormValues(product)).toEqual({
      name: "Ada",
      sku: "SKU-7",
      brand: "",
      unit: "kg",
      sellingPrice: "12",
      costPrice: "10",
      imageUrl: "",
      description: "",
      status: "archive",
    })
  })
})
