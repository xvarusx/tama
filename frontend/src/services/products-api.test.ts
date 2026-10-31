import { beforeEach, describe, expect, it, vi } from "vitest"
import { api } from "@/lib/api"
import {
  createProduct,
  getProducts,
  getProductsTotal,
  setProductStatus,
  updateProduct,
  type Product,
  type ProductInput,
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

const makeProducts = (count: number): Product[] =>
  Array.from({ length: count }, (_, index) => ({
    id: index + 1,
    sku: `SKU-${index + 1}`,
    name: `Product ${index + 1}`,
    description: null,
    brand: null,
    unit: "kg",
    sellingPrice: "12",
    costPrice: "10",
    imageUrl: null,
    status: "active",
    createdAt: "2026-01-01T00:00:00.000Z",
    updatedAt: "2026-01-01T00:00:00.000Z",
    category: null,
  }))

const input: ProductInput = {
  sku: "SKU-7",
  name: "Ada",
  description: null,
  brand: null,
  unit: "kg",
  sellingPrice: "12",
  costPrice: "10",
  imageUrl: null,
  status: "active",
}

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
  status: "active",
  createdAt: "2026-01-01T00:00:00.000Z",
  updatedAt: "2026-01-01T00:00:00.000Z",
  category: null,
}

const pageCall = (page: number) => [
  "/api/product",
  { params: { page, limit: 100 } },
]

beforeEach(() => {
  vi.clearAllMocks()
})

describe("getProducts", () => {
  it("requests a page and returns the array", async () => {
    vi.mocked(api.get).mockResolvedValueOnce(response([product]))

    await expect(getProducts(2, 25)).resolves.toEqual([product])

    expect(api.get).toHaveBeenCalledWith("/api/product", {
      params: { page: 2, limit: 25 },
    })
  })
})

describe("getProductsTotal", () => {
  it("sums the last partial page", async () => {
    vi.mocked(api.get)
      .mockResolvedValueOnce(response(makeProducts(100)))
      .mockResolvedValueOnce(response(makeProducts(25)))

    await expect(getProductsTotal()).resolves.toBe(125)

    expect(api.get).toHaveBeenNthCalledWith(1, ...pageCall(1))
    expect(api.get).toHaveBeenNthCalledWith(2, ...pageCall(2))
  })

  it("counts a full page and stops on the empty one", async () => {
    vi.mocked(api.get)
      .mockResolvedValueOnce(response(makeProducts(100)))
      .mockResolvedValueOnce(response([]))

    await expect(getProductsTotal()).resolves.toBe(100)

    expect(api.get).toHaveBeenNthCalledWith(1, ...pageCall(1))
    expect(api.get).toHaveBeenNthCalledWith(2, ...pageCall(2))
  })

  it("returns zero when the first page is empty", async () => {
    vi.mocked(api.get).mockResolvedValueOnce(response([]))

    await expect(getProductsTotal()).resolves.toBe(0)

    expect(api.get).toHaveBeenCalledTimes(1)
    expect(api.get).toHaveBeenCalledWith(...pageCall(1))
  })

  it("fails after the safety page limit instead of walking forever", async () => {
    vi.mocked(api.get).mockResolvedValue(response(makeProducts(100)))

    await expect(getProductsTotal()).rejects.toThrow(
      "Unable to determine product total.",
    )
    expect(api.get).toHaveBeenCalledTimes(1000)
  })
})

describe("createProduct", () => {
  it("posts the input and returns the created product", async () => {
    vi.mocked(api.post).mockResolvedValueOnce(response(product))

    await expect(createProduct(input)).resolves.toBe(product)

    expect(api.post).toHaveBeenCalledWith("/api/product/", input)
  })

  it("rejects a 2xx body that carries an error instead of a product", async () => {
    vi.mocked(api.post).mockResolvedValueOnce(
      response({ error: "Invalid image URL" }),
    )

    await expect(createProduct(input)).rejects.toThrow("Invalid image URL")
  })
})

describe("updateProduct", () => {
  it("puts the input and returns the updated product", async () => {
    vi.mocked(api.put).mockResolvedValueOnce(response(product))

    await expect(updateProduct(7, input)).resolves.toBe(product)

    expect(api.put).toHaveBeenCalledWith("/api/product/7", input)
  })
})

describe("setProductStatus", () => {
  it("re-sends the full product with the new status", async () => {
    const archived: Product = { ...product, status: "archive" }
    vi.mocked(api.put).mockResolvedValueOnce(response(archived))

    await expect(setProductStatus(product, "archive")).resolves.toBe(archived)

    expect(api.put).toHaveBeenCalledWith(
      "/api/product/7",
      {
        sku: product.sku,
        name: product.name,
        description: product.description,
        brand: product.brand,
        unit: product.unit,
        sellingPrice: product.sellingPrice,
        costPrice: product.costPrice,
        imageUrl: product.imageUrl,
        status: "archive",
      },
    )
  })
})
