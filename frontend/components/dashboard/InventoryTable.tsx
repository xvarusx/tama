"use client";

import Pagination from "@/components/tables/Pagination";
import Badge from "@/components/ui/badge/Badge";
import {
  Table,
  TableBody,
  TableCell,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import {
  filterByQuery,
  getInventoryStatus,
  inventory,
  paginate,
} from "@/lib/dashboard-data";
import { useState } from "react";

const PAGE_SIZE = 5;

const statusColor = {
  ok: "success",
  low: "warning",
  out: "error",
} as const;

const statusLabel = {
  ok: "In stock",
  low: "Low stock",
  out: "Out of stock",
} as const;

export default function InventoryTable() {
  const [query, setQuery] = useState("");
  const [status, setStatus] = useState<"all" | "ok" | "low" | "out">("all");
  const [page, setPage] = useState(1);

  const searched = filterByQuery(inventory, query, (item) => [
    item.sku,
    item.product,
    item.warehouse,
  ]);
  const filtered = searched.filter((item) =>
    status === "all" ? true : getInventoryStatus(item) === status,
  );
  const { items, totalPages, page: currentPage } = paginate(
    filtered,
    page,
    PAGE_SIZE,
  );

  return (
    <div className="mt-4 overflow-hidden rounded-2xl border border-gray-200 bg-white md:mt-6 dark:border-gray-800 dark:bg-white/3">
      <div className="flex flex-col gap-4 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
        <div>
          <h3 className="text-lg font-semibold text-gray-800 dark:text-white/90">
            Stock levels
          </h3>
          <p className="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">
            {filtered.length} item{filtered.length === 1 ? "" : "s"}
          </p>
        </div>
        <div className="flex w-full flex-col gap-3 sm:max-w-md sm:flex-row">
          <input
            type="search"
            value={query}
            onChange={(event) => {
              setQuery(event.target.value);
              setPage(1);
            }}
            placeholder="Search SKU, product, or warehouse"
            aria-label="Search inventory"
            className="h-11 w-full rounded-lg border border-gray-200 bg-transparent px-4 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-800 dark:bg-white/3 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
          />
          <select
            value={status}
            onChange={(event) => {
              setStatus(
                event.target.value as "all" | "ok" | "low" | "out",
              );
              setPage(1);
            }}
            aria-label="Filter by stock status"
            className="h-11 rounded-lg border border-gray-200 bg-transparent px-3 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-800 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
          >
            <option value="all">All statuses</option>
            <option value="ok">In stock</option>
            <option value="low">Low stock</option>
            <option value="out">Out of stock</option>
          </select>
        </div>
      </div>

      <div className="max-w-full overflow-x-auto">
        <Table>
          <TableHeader className="border-y border-gray-100 dark:border-gray-800">
            <TableRow>
              {[
                "SKU",
                "Product",
                "Warehouse",
                "Quantity",
                "Reorder level",
                "Status",
              ].map((heading) => (
                <TableCell
                  key={heading}
                  isHeader
                  className="px-5 py-3 text-start text-theme-xs font-medium text-gray-500 sm:px-6 dark:text-gray-400"
                >
                  {heading}
                </TableCell>
              ))}
            </TableRow>
          </TableHeader>
          <TableBody className="divide-y divide-gray-100 dark:divide-gray-800">
            {items.length === 0 ? (
              <TableRow>
                <td
                  colSpan={6}
                  className="px-5 py-8 text-center text-theme-sm text-gray-500 sm:px-6 dark:text-gray-400"
                >
                  No inventory matches your filters.
                </td>
              </TableRow>
            ) : (
              items.map((item) => {
                const itemStatus = getInventoryStatus(item);
                return (
                  <TableRow key={item.id}>
                    <TableCell className="px-5 py-4 text-start text-theme-sm font-medium text-gray-800 sm:px-6 dark:text-white/90">
                      {item.sku}
                    </TableCell>
                    <TableCell className="px-5 py-4 text-start text-theme-sm text-gray-500 sm:px-6 dark:text-gray-400">
                      {item.product}
                    </TableCell>
                    <TableCell className="px-5 py-4 text-start text-theme-sm text-gray-500 sm:px-6 dark:text-gray-400">
                      {item.warehouse}
                    </TableCell>
                    <TableCell className="px-5 py-4 text-start text-theme-sm text-gray-800 sm:px-6 dark:text-white/90">
                      {item.quantity}
                    </TableCell>
                    <TableCell className="px-5 py-4 text-start text-theme-sm text-gray-500 sm:px-6 dark:text-gray-400">
                      {item.reorderLevel}
                    </TableCell>
                    <TableCell className="px-5 py-4 text-start sm:px-6">
                      <Badge size="sm" color={statusColor[itemStatus]}>
                        {statusLabel[itemStatus]}
                      </Badge>
                    </TableCell>
                  </TableRow>
                );
              })
            )}
          </TableBody>
        </Table>
      </div>

      {totalPages > 1 && (
        <div className="flex justify-end border-t border-gray-100 px-5 py-4 sm:px-6 dark:border-gray-800">
          <Pagination
            currentPage={currentPage}
            totalPages={totalPages}
            onPageChange={setPage}
          />
        </div>
      )}
    </div>
  );
}
