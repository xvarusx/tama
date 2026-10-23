import { describe, expect, it } from "vitest";
import {
  clients,
  computeInventorySummary,
  filterByQuery,
  getInventoryStatus,
  getUnreadCount,
  inventory,
  notifications,
  paginate,
  products,
} from "@/lib/dashboard-data";

describe("sample data", () => {
  it("has the pinned client and product used by the UI checks", () => {
    expect(clients[0]).toMatchObject({
      name: "Acme Corporation",
      slug: "acme-corporation",
    });
    expect(
      products.some(
        (product) =>
          product.name === "POS Terminal" && product.sku === "POS-1001",
      ),
    ).toBe(true);
  });

  it("has exactly three unread notifications", () => {
    expect(notifications.filter((notification) => !notification.read)).toHaveLength(
      3,
    );
  });
});

describe("filterByQuery", () => {
  it("matches case-insensitively across the selected fields", () => {
    const results = filterByQuery(products, "POS", (product) => [
      product.name,
      product.sku,
    ]);
    expect(results.length).toBeGreaterThan(0);
  });

  it("returns an empty array when nothing matches", () => {
    expect(filterByQuery(products, "zzzz", (product) => [product.name])).toEqual(
      [],
    );
  });

  it("returns the original array for a blank query", () => {
    expect(filterByQuery(products, "   ", (product) => [product.name])).toBe(
      products,
    );
  });
});

describe("paginate", () => {
  it("slices the requested page and reports the page count", () => {
    expect(paginate([1, 2, 3, 4, 5, 6, 7], 1, 5)).toMatchObject({
      items: [1, 2, 3, 4, 5],
      totalPages: 2,
      page: 1,
    });
  });

  it("keeps one empty page for an empty list", () => {
    expect(paginate([], 1, 5)).toEqual({ items: [], totalPages: 1, page: 1 });
  });

  it("clamps out-of-range pages", () => {
    expect(paginate([1, 2, 3], 99, 5)).toMatchObject({ page: 1 });
    expect(paginate([1, 2, 3], 0, 5)).toMatchObject({ page: 1 });
  });
});

describe("inventory helpers", () => {
  it("classifies out, low, and ok stock", () => {
    expect(getInventoryStatus({ ...inventory[0], quantity: 0 })).toBe("out");
    expect(
      getInventoryStatus({ ...inventory[0], quantity: 1, reorderLevel: 5 }),
    ).toBe("low");
    expect(
      getInventoryStatus({ ...inventory[0], quantity: 50, reorderLevel: 5 }),
    ).toBe("ok");
  });

  it("summarizes SKUs, stock levels, and units", () => {
    const summary = computeInventorySummary(inventory);
    expect(summary.totalSkus).toBe(inventory.length);
    expect(summary.totalUnits).toBe(
      inventory.reduce((total, item) => total + item.quantity, 0),
    );
    expect(summary.lowStock + summary.outOfStock).toBe(
      inventory.filter((item) => getInventoryStatus(item) !== "ok").length,
    );
  });
});

describe("getUnreadCount", () => {
  it("counts unread notifications", () => {
    expect(getUnreadCount(notifications)).toBe(
      notifications.filter((notification) => !notification.read).length,
    );
  });
});
