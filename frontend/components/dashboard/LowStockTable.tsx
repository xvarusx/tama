import Badge from "@/components/ui/badge/Badge";
import {
  Table,
  TableBody,
  TableCell,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { getInventoryStatus, inventory } from "@/lib/dashboard-data";
import Link from "next/link";

export default function LowStockTable() {
  const lowStockItems = inventory.filter(
    (item) => getInventoryStatus(item) !== "ok",
  );

  return (
    <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/3">
      <div className="flex items-center justify-between px-5 py-4 sm:px-6">
        <h3 className="text-lg font-semibold text-gray-800 dark:text-white/90">
          Low stock
        </h3>
        <Link
          href="/dashboard/inventory"
          className="text-sm font-medium text-brand-500 hover:text-brand-600 dark:text-brand-400"
        >
          View inventory
        </Link>
      </div>

      <div className="max-w-full overflow-x-auto">
        <Table>
          <TableHeader className="border-y border-gray-100 dark:border-gray-800">
            <TableRow>
              <TableCell
                isHeader
                className="px-5 py-3 text-start text-theme-xs font-medium text-gray-500 sm:px-6 dark:text-gray-400"
              >
                Product
              </TableCell>
              <TableCell
                isHeader
                className="px-5 py-3 text-start text-theme-xs font-medium text-gray-500 sm:px-6 dark:text-gray-400"
              >
                SKU
              </TableCell>
              <TableCell
                isHeader
                className="px-5 py-3 text-end text-theme-xs font-medium text-gray-500 sm:px-6 dark:text-gray-400"
              >
                Qty
              </TableCell>
              <TableCell
                isHeader
                className="px-5 py-3 text-start text-theme-xs font-medium text-gray-500 sm:px-6 dark:text-gray-400"
              >
                Status
              </TableCell>
            </TableRow>
          </TableHeader>
          <TableBody className="divide-y divide-gray-100 dark:divide-gray-800">
            {lowStockItems.map((item) => {
              const status = getInventoryStatus(item);
              return (
                <TableRow key={item.id}>
                  <TableCell className="px-5 py-4 text-start text-theme-sm font-medium text-gray-800 sm:px-6 dark:text-white/90">
                    {item.product}
                  </TableCell>
                  <TableCell className="px-5 py-4 text-start text-theme-sm text-gray-500 sm:px-6 dark:text-gray-400">
                    {item.sku}
                  </TableCell>
                  <TableCell className="px-5 py-4 text-end text-theme-sm text-gray-800 sm:px-6 dark:text-white/90">
                    {item.quantity}
                  </TableCell>
                  <TableCell className="px-5 py-4 text-start sm:px-6">
                    <Badge
                      size="sm"
                      color={status === "out" ? "error" : "warning"}
                    >
                      {status === "out" ? "Out of stock" : "Low stock"}
                    </Badge>
                  </TableCell>
                </TableRow>
              );
            })}
          </TableBody>
        </Table>
      </div>
    </div>
  );
}
