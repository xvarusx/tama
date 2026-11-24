import { describe, expect, it } from "vitest"
import type { Product } from "@/services/products-api"
import {
  createEmptyProductForm,
  createEmptyVariant,
  toProductFormValues,
  toProductInput,
  toProductUpdateInput,
  validateProductForm,
  type ProductFormValues,
} from "./product-validation"

const uuid = (n: number): string =>
  `00000000-0000-7000-8000-${String(n).padStart(12, "0")}`

const PRODUCT_ID = uuid(7)
const BRAND_ID = uuid(1)
const UNIT_ID = uuid(2)
const CATEGORY_ID = uuid(3)
const VARIANT_ID = uuid(11)

const validValues = (
  overrides: Partial<ProductFormValues> = {},
): ProductFormValues => ({
  name: "Product",
  brandId: BRAND_ID,
  unitId: UNIT_ID,
  categoryId: "",
  description: "",
  variants: [
    { sku: "SKU-1", costPrice: "10.00", sellingPrice: "12.00", barcode: "" },
  ],
  ...overrides,
})

const product: Product = {
  id: PRODUCT_ID,
  type: "product",
  attributes: {
    name: "Ada",
    description: null,
    brand: { id: BRAND_ID, name: "Acme" },
    unit: { id: UNIT_ID, code: "pcs" },
    category: { id: CATEGORY_ID, name: "Monitors" },
    variants: [
      {
        id: VARIANT_ID,
        sku: "SKU-7",
        barcode: null,
        options: null,
        sellingPrice: "12.00",
        costPrice: "10.00",
      },
    ],
    media: [],
    createdAt: "2026-01-01T00:00:00+00:00",
    updatedAt: "2026-01-01T00:00:00+00:00",
  },
}

describe("createEmptyProductForm", () => {
  it("starts with exactly one empty variant", () => {
    const values = createEmptyProductForm()

    expect(values.variants).toEqual([
      { sku: "", costPrice: "", sellingPrice: "", barcode: "" },
    ])
  })
})

describe("validateProductForm", () => {
  it("requires the product and first-variant fields on an empty form", () => {
    const errors = validateProductForm(createEmptyProductForm())

    expect(errors.name).toBe("Name is required.")
    expect(errors.brandId).toBe("Select a brand.")
    expect(errors.unitId).toBe("Select a unit.")
    expect(errors.variantsMessage).toBeUndefined()
    expect(errors.variants?.[0]).toEqual({
      sku: "SKU is required.",
      costPrice: "Cost price is required.",
      sellingPrice: "Selling price is required.",
    })
  })

  it("requires at least one variant", () => {
    const errors = validateProductForm(validValues({ variants: [] }))

    expect(errors.variantsMessage).toBe("Add at least one variant.")
  })

  it("accepts prices with up to two decimals", () => {
    const errors = validateProductForm(
      validValues({
        variants: [
          {
            sku: "SKU-1",
            costPrice: "10.5",
            sellingPrice: "12.50",
            barcode: "",
          },
        ],
      }),
    )

    expect(errors).toEqual({})
  })

  it.each(["12.555", "-1", "abc"])("rejects the price %s", (price) => {
    const errors = validateProductForm(
      validValues({
        variants: [
          { sku: "SKU-1", costPrice: price, sellingPrice: price, barcode: "" },
        ],
      }),
    )

    expect(errors.variants?.[0]?.costPrice).toBe(
      "Use a number with at most 2 decimals.",
    )
    expect(errors.variants?.[0]?.sellingPrice).toBe(
      "Use a number with at most 2 decimals.",
    )
  })

  it("flags a duplicate SKU on the later variant, ignoring case and spaces", () => {
    const errors = validateProductForm(
      validValues({
        variants: [
          { sku: "ABC", costPrice: "1.00", sellingPrice: "2.00", barcode: "" },
          {
            sku: "  abc ",
            costPrice: "1.00",
            sellingPrice: "2.00",
            barcode: "",
          },
        ],
      }),
    )

    expect(errors.variants?.[0]?.sku).toBeUndefined()
    expect(errors.variants?.[1]?.sku).toBe(
      "This SKU is used by another variant.",
    )
  })

  it("accepts a fully valid multi-variant form", () => {
    const errors = validateProductForm(
      validValues({
        variants: [
          { sku: "SKU-S", costPrice: "8.00", sellingPrice: "15.00", barcode: "" },
          {
            sku: "SKU-M",
            costPrice: "8.00",
            sellingPrice: "15.00",
            barcode: "123",
          },
        ],
      }),
    )

    expect(errors).toEqual({})
  })
})

describe("toProductInput", () => {
  it("trims text, keeps UUID strings and turns empty optionals into null", () => {
    expect(
      toProductInput(
        validValues({
          name: "  Ada  ",
          brandId: `  ${BRAND_ID} `,
          unitId: UNIT_ID,
          categoryId: "",
          description: "  ",
          variants: [
            {
              sku: " SKU-1 ",
              costPrice: " 10.00 ",
              sellingPrice: "12.00",
              barcode: "  ",
            },
          ],
        }),
      ),
    ).toEqual({
      name: "Ada",
      brandId: BRAND_ID,
      unitId: UNIT_ID,
      categoryId: null,
      description: null,
      variants: [
        {
          sku: "SKU-1",
          costPrice: "10.00",
          sellingPrice: "12.00",
          barcode: null,
        },
      ],
    })
  })

  it("keeps a category when one is selected", () => {
    expect(
      toProductInput(validValues({ categoryId: CATEGORY_ID })).categoryId,
    ).toBe(CATEGORY_ID)
  })
})

describe("toProductUpdateInput", () => {
  it("includes the variants so PUT can sync them", () => {
    expect(toProductUpdateInput(validValues())).toEqual({
      name: "Product",
      brandId: BRAND_ID,
      unitId: UNIT_ID,
      categoryId: null,
      description: null,
      variants: [
        {
          sku: "SKU-1",
          costPrice: "10.00",
          sellingPrice: "12.00",
          barcode: null,
        },
      ],
    })
  })

  it("keeps variant ids and options", () => {
    const input = toProductUpdateInput(
      validValues({
        variants: [
          {
            id: VARIANT_ID,
            sku: "SKU-7",
            costPrice: "10.00",
            sellingPrice: "12.00",
            barcode: "",
            options: { size: "M" },
          },
        ],
      }),
    )

    expect(input.variants[0]).toEqual({
      id: VARIANT_ID,
      sku: "SKU-7",
      costPrice: "10.00",
      sellingPrice: "12.00",
      barcode: null,
      options: { size: "M" },
    })
  })
})

describe("toProductFormValues", () => {
  it("reads the nested attributes", () => {
    expect(toProductFormValues(product)).toEqual({
      name: "Ada",
      brandId: BRAND_ID,
      unitId: UNIT_ID,
      categoryId: CATEGORY_ID,
      description: "",
      variants: [
        {
          id: VARIANT_ID,
          sku: "SKU-7",
          costPrice: "10.00",
          sellingPrice: "12.00",
          barcode: "",
          options: null,
        },
      ],
    })
  })

  it("keeps variant ids and options", () => {
    expect(toProductFormValues(product).variants[0]).toEqual({
      id: VARIANT_ID,
      sku: "SKU-7",
      costPrice: "10.00",
      sellingPrice: "12.00",
      barcode: "",
      options: null,
    })
  })

  it("turns a missing category into an empty selection", () => {
    const noCategory: Product = {
      ...product,
      attributes: { ...product.attributes, category: null },
    }

    expect(toProductFormValues(noCategory).categoryId).toBe("")
  })
})

describe("createEmptyVariant", () => {
  it("returns blank strings", () => {
    expect(createEmptyVariant()).toEqual({
      sku: "",
      costPrice: "",
      sellingPrice: "",
      barcode: "",
    })
  })
})
