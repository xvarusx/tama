import { api } from "@/lib/api";

/**
 * Product requests stay relative so the Vite proxy forwards them through the
 * gateway in development without triggering cross-origin requests.
 */
export const PRODUCTS_API_URL = "";

export const PRODUCT_STATUS = {
  active: "Active",
  archive: "Archived",
} as const;

export type ProductStatus = keyof typeof PRODUCT_STATUS;

export interface ProductCategory {
  id: number;
  name: string;
}

export interface Product {
  id: number;
  sku: string;
  name: string;
  description: string | null;
  brand: string | null;
  unit: string;
  sellingPrice: string;
  costPrice: string;
  imageUrl: string | null;
  status: ProductStatus;
  createdAt: string;
  updatedAt: string;
  category: ProductCategory | null;
}

export interface ProductInput {
  sku: string;
  name: string;
  description: string | null;
  brand: string | null;
  unit: string;
  sellingPrice: string;
  costPrice: string;
  imageUrl: string | null;
  status: ProductStatus;
}

function logRequestError(action: string, error: unknown) {
  console.error(action, error instanceof Error ? error.message : error);
}

function ensureProduct(data: unknown): Product {
  if (data && typeof data === "object" && "error" in data && !("id" in data)) {
    throw new Error(String((data as { error: unknown }).error));
  }

  return data as Product;
}

export async function getProducts(
  page: number,
  limit: number,
): Promise<Product[]> {
  try {
    const response = await api.get<Product[]>("/api/product", {
      baseURL: PRODUCTS_API_URL,
      params: { page, limit },
    });

    return response.data;
  } catch (error) {
    logRequestError("Failed to fetch products:", error);
    throw error;
  }
}

const PRODUCTS_PAGE_SIZE = 100;
const MAX_PRODUCT_PAGES = 1_000;

export async function getProductsTotal(): Promise<number> {
  let total = 0;

  for (let page = 1; page <= MAX_PRODUCT_PAGES; page += 1) {
    const products = await getProducts(page, PRODUCTS_PAGE_SIZE);
    total += products.length;

    if (products.length < PRODUCTS_PAGE_SIZE) return total;
  }

  throw new Error("Unable to determine product total.");
}

export async function createProduct(input: ProductInput): Promise<Product> {
  try {
    const response = await api.post<Product>("/api/product/", input, {
      baseURL: PRODUCTS_API_URL,
    });

    return ensureProduct(response.data);
  } catch (error) {
    logRequestError("Failed to create product:", error);
    throw error;
  }
}

export async function updateProduct(
  id: number,
  input: ProductInput,
): Promise<Product> {
  try {
    const response = await api.put<Product>(`/api/product/${id}`, input, {
      baseURL: PRODUCTS_API_URL,
    });

    return ensureProduct(response.data);
  } catch (error) {
    logRequestError("Failed to update product:", error);
    throw error;
  }
}

export async function setProductStatus(
  product: Product,
  status: ProductStatus,
): Promise<Product> {
  return updateProduct(product.id, {
    sku: product.sku,
    name: product.name,
    description: product.description,
    brand: product.brand,
    unit: product.unit,
    sellingPrice: product.sellingPrice,
    costPrice: product.costPrice,
    imageUrl: product.imageUrl,
    status,
  });
}

export default getProducts;
