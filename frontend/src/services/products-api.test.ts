import { beforeEach, describe, expect, it, vi } from "vitest"
import { api } from "@/lib/api"
import {
  createProduct,
  deleteProduct,
  getBrands,
  getCategories,
  getProduct,
  getProducts,
  getUnits,
  updateProduct,
  type Product,
  type ProductInput,
  type ProductPage,
} from "./products-api"

vi.mock("@/lib/api", () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  },
}))

const response = (data: unknown) => ({ data }) as never

const product: Product = {
  id: 7,
  type: "product",
  attributes: {
    name: "Ada",
    description: null,
    brand: { id: 1, name: "Acme" },
    unit: { id: 2, code: "pcs" },
    category: null,
    variants: [
      {
        id: 11,
        sku: "SKU-7",
        barcode: null,
        options: null,
        sellingPrice: "12.00",
        costPrice: "10.00",
      },
    ],
    createdAt: "2026-01-01T00:00:00+00:00",
    updatedAt: "2026-01-01T00:00:00+00:00",
  },
}

const pagination = {
  currentPage: 2,
  totalPages: 3,
  pageSize: 25,
  totalItems: 51,
  links: { self: "/api/v1/products?page=2" },
}

const page: ProductPage = { items: [product], pagination }

const input: ProductInput = {
  name: "Ada",
  brandId: 1,
  unitId: 2,
  categoryId: null,
  description: null,
  variants: [
    { sku: "SKU-7", costPrice: "10.00", sellingPrice: "12.00", barcode: null },
  ],
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe("getProducts", () => {
  it("requests a page and unwraps the envelope", async () => {
    vi.mocked(api.get).mockResolvedValueOnce(
      response({ status: "success", data: [product], pagination }),
    )

    await expect(
      getProducts({ page: 2, pageSize: 25, search: "mon" }),
    ).resolves.toEqual(page)

    expect(api.get).toHaveBeenCalledWith("/api/product/products", {
      params: { page: 2, pageSize: 25, search: "mon" },
    })
  })

  it("omits empty filters", async () => {
    vi.mocked(api.get).mockResolvedValueOnce(
      response({ status: "success", data: [], pagination }),
    )

    await getProducts({ page: 1, pageSize: 20 })

    expect(api.get).toHaveBeenCalledWith("/api/product/products", {
      params: { page: 1, pageSize: 20 },
    })
  })

  it("rejects an error envelope", async () => {
    vi.mocked(api.get).mockResolvedValueOnce(
      response({
        status: "error",
        error: { code: "INTERNAL_ERROR", message: "Boom" },
      }),
    )

    await expect(getProducts({ page: 1, pageSize: 20 })).rejects.toThrow(
      "Boom",
    )
  })
})

describe("getProduct", () => {
  it("fetches one product", async () => {
    vi.mocked(api.get).mockResolvedValueOnce(
      response({ status: "success", data: product }),
    )

    await expect(getProduct(7)).resolves.toEqual(product)

    expect(api.get).toHaveBeenCalledWith("/api/product/products/7")
  })
})

describe("createProduct", () => {
  it("posts the product with its variants and unwraps it", async () => {
    vi.mocked(api.post).mockResolvedValueOnce(
      response({ status: "success", data: product }),
    )

    await expect(createProduct(input)).resolves.toEqual(product)

    expect(api.post).toHaveBeenCalledWith("/api/product/products", input)
  })
})

describe("updateProduct", () => {
  it("puts only the product fields (variants are immutable)", async () => {
    vi.mocked(api.put).mockResolvedValueOnce(
      response({ status: "success", data: product }),
    )

    const { variants: _variants, ...fields } = input
    await expect(updateProduct(7, fields)).resolves.toEqual(product)

    expect(api.put).toHaveBeenCalledWith("/api/product/products/7", fields)
  })
})

describe("deleteProduct", () => {
  it("deletes the product", async () => {
    vi.mocked(api.delete).mockResolvedValueOnce(response(undefined))

    await expect(deleteProduct(7)).resolves.toBeUndefined()

    expect(api.delete).toHaveBeenCalledWith("/api/product/products/7")
  })
})

describe("reference data", () => {
  it("loads brands", async () => {
    const brands = [{ id: 1, name: "Acme", slug: "acme" }]
    vi.mocked(api.get).mockResolvedValueOnce(
      response({ status: "success", data: brands }),
    )

    await expect(getBrands()).resolves.toEqual(brands)
    expect(api.get).toHaveBeenCalledWith("/api/product/brands")
  })

  it("loads units", async () => {
    const units = [{ id: 2, code: "pcs", allowDecimal: false }]
    vi.mocked(api.get).mockResolvedValueOnce(
      response({ status: "success", data: units }),
    )

    await expect(getUnits()).resolves.toEqual(units)
    expect(api.get).toHaveBeenCalledWith("/api/product/units")
  })

  it("loads categories", async () => {
    const categories = [{ id: 3, name: "Monitors", slug: "monitors" }]
    vi.mocked(api.get).mockResolvedValueOnce(
      response({ status: "success", data: categories }),
    )

    await expect(getCategories()).resolves.toEqual(categories)
    expect(api.get).toHaveBeenCalledWith("/api/product/categories")
  })
})
