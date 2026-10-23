import Badge from "@/components/ui/badge/Badge";
import {
  Table,
  TableBody,
  TableCell,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { clients } from "@/lib/dashboard-data";
import Link from "next/link";

const currency = new Intl.NumberFormat("en-US", {
  style: "currency",
  currency: "USD",
  maximumFractionDigits: 0,
});

export default function RecentClientsTable() {
  return (
    <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/3">
      <div className="flex items-center justify-between px-5 py-4 sm:px-6">
        <h3 className="text-lg font-semibold text-gray-800 dark:text-white/90">
          Recent clients
        </h3>
        <Link
          href="/dashboard/clients"
          className="text-sm font-medium text-brand-500 hover:text-brand-600 dark:text-brand-400"
        >
          View all
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
                Client
              </TableCell>
              <TableCell
                isHeader
                className="px-5 py-3 text-start text-theme-xs font-medium text-gray-500 sm:px-6 dark:text-gray-400"
              >
                Company
              </TableCell>
              <TableCell
                isHeader
                className="px-5 py-3 text-start text-theme-xs font-medium text-gray-500 sm:px-6 dark:text-gray-400"
              >
                Status
              </TableCell>
              <TableCell
                isHeader
                className="px-5 py-3 text-end text-theme-xs font-medium text-gray-500 sm:px-6 dark:text-gray-400"
              >
                Total spent
              </TableCell>
            </TableRow>
          </TableHeader>
          <TableBody className="divide-y divide-gray-100 dark:divide-gray-800">
            {clients.slice(0, 5).map((client) => (
              <TableRow key={client.id}>
                <TableCell className="px-5 py-4 text-start text-theme-sm font-medium text-gray-800 sm:px-6 dark:text-white/90">
                  <Link
                    href={`/dashboard/clients/${client.slug}`}
                    className="hover:text-brand-500"
                  >
                    {client.name}
                  </Link>
                </TableCell>
                <TableCell className="px-5 py-4 text-start text-theme-sm text-gray-500 sm:px-6 dark:text-gray-400">
                  {client.company}
                </TableCell>
                <TableCell className="px-5 py-4 text-start sm:px-6">
                  <Badge
                    size="sm"
                    color={client.status === "active" ? "success" : "light"}
                  >
                    {client.status === "active" ? "Active" : "Inactive"}
                  </Badge>
                </TableCell>
                <TableCell className="px-5 py-4 text-end text-theme-sm text-gray-800 sm:px-6 dark:text-white/90">
                  {currency.format(client.totalSpent)}
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>
    </div>
  );
}
