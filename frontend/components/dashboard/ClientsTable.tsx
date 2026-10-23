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
import { clients, filterByQuery, paginate } from "@/lib/dashboard-data";
import Link from "next/link";
import { useState } from "react";

const PAGE_SIZE = 5;

export default function ClientsTable() {
  const [query, setQuery] = useState("");
  const [page, setPage] = useState(1);

  const filtered = filterByQuery(clients, query, (client) => [
    client.name,
    client.company,
    client.email,
    client.city,
  ]);
  const { items, totalPages, page: currentPage } = paginate(
    filtered,
    page,
    PAGE_SIZE,
  );

  return (
    <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/3">
      <div className="flex flex-col gap-4 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
        <div>
          <h3 className="text-lg font-semibold text-gray-800 dark:text-white/90">
            All clients
          </h3>
          <p className="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">
            {filtered.length} client{filtered.length === 1 ? "" : "s"}
          </p>
        </div>
        <div className="relative w-full sm:max-w-xs">
          <input
            type="search"
            value={query}
            onChange={(event) => {
              setQuery(event.target.value);
              setPage(1);
            }}
            placeholder="Search name, company, email, or city"
            aria-label="Search clients"
            className="h-11 w-full rounded-lg border border-gray-200 bg-transparent px-4 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-800 dark:bg-white/3 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
          />
        </div>
      </div>

      <div className="max-w-full overflow-x-auto">
        <Table>
          <TableHeader className="border-y border-gray-100 dark:border-gray-800">
            <TableRow>
              {["Client", "Company", "Phone", "Status", "Since", ""].map(
                (heading, index) => (
                  <TableCell
                    key={`${heading}-${index}`}
                    isHeader
                    className="px-5 py-3 text-start text-theme-xs font-medium text-gray-500 sm:px-6 dark:text-gray-400"
                  >
                    {heading}
                  </TableCell>
                ),
              )}
            </TableRow>
          </TableHeader>
          <TableBody className="divide-y divide-gray-100 dark:divide-gray-800">
            {items.length === 0 ? (
              <TableRow>
                <td
                  colSpan={6}
                  className="px-5 py-8 text-center text-theme-sm text-gray-500 sm:px-6 dark:text-gray-400"
                >
                  No clients match your search.
                </td>
              </TableRow>
            ) : (
              items.map((client) => (
                <TableRow key={client.id}>
                  <TableCell className="px-5 py-4 text-start sm:px-6">
                    <span className="block text-theme-sm font-medium text-gray-800 dark:text-white/90">
                      {client.name}
                    </span>
                    <span className="mt-0.5 block text-theme-xs text-gray-500 dark:text-gray-400">
                      {client.email}
                    </span>
                  </TableCell>
                  <TableCell className="px-5 py-4 text-start text-theme-sm text-gray-500 sm:px-6 dark:text-gray-400">
                    {client.company}
                  </TableCell>
                  <TableCell className="px-5 py-4 text-start text-theme-sm text-gray-500 sm:px-6 dark:text-gray-400">
                    {client.phone}
                  </TableCell>
                  <TableCell className="px-5 py-4 text-start sm:px-6">
                    <Badge
                      size="sm"
                      color={client.status === "active" ? "success" : "light"}
                    >
                      {client.status === "active" ? "Active" : "Inactive"}
                    </Badge>
                  </TableCell>
                  <TableCell className="px-5 py-4 text-start text-theme-sm text-gray-500 sm:px-6 dark:text-gray-400">
                    {client.since}
                  </TableCell>
                  <TableCell className="px-5 py-4 text-start sm:px-6">
                    <Link
                      href={`/dashboard/clients/${client.slug}`}
                      className="text-sm font-medium text-brand-500 hover:text-brand-600 dark:text-brand-400"
                    >
                      View
                    </Link>
                  </TableCell>
                </TableRow>
              ))
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
