import PageBreadcrumb from "@/components/common/PageBreadCrumb";
import LowStockTable from "@/components/dashboard/LowStockTable";
import MetricCard from "@/components/dashboard/MetricCard";
import OrdersChart from "@/components/dashboard/OrdersChart";
import RecentClientsTable from "@/components/dashboard/RecentClientsTable";
import RevenueChart from "@/components/dashboard/RevenueChart";
import UnreadNotificationsCard from "@/components/dashboard/UnreadNotificationsCard";
import {
  BoxCubeIcon,
  BoxIconLine,
  GroupIcon,
} from "@/icons";
import { getCurrentUser } from "@/lib/auth/dal";
import {
  clients,
  computeInventorySummary,
  inventory,
  products,
} from "@/lib/dashboard-data";

export const metadata = {
  title: "Dashboard | ERP",
};

export const instant = false;

export default async function DashboardPage() {
  await getCurrentUser();
  const summary = computeInventorySummary(inventory);
  const lowStock = summary.lowStock + summary.outOfStock;

  return (
    <div>
      <PageBreadcrumb pageTitle="Dashboard" />

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6 xl:grid-cols-4">
        <MetricCard
          label="Total clients"
          value={String(clients.length)}
          change="12.5%"
          trend="up"
          icon={<GroupIcon className="size-6 text-gray-800 dark:text-white/90" />}
        />
        <MetricCard
          label="Products"
          value={String(products.length)}
          change="3 new"
          trend="up"
          icon={
            <BoxCubeIcon className="size-6 text-gray-800 dark:text-white/90" />
          }
        />
        <MetricCard
          label="Low stock"
          value={String(lowStock)}
          icon={
            <BoxIconLine className="size-6 text-gray-800 dark:text-white/90" />
          }
        />
        <UnreadNotificationsCard />
      </div>

      <div className="mt-4 grid grid-cols-12 gap-4 md:mt-6 md:gap-6">
        <div className="col-span-12 xl:col-span-7">
          <OrdersChart />
        </div>
        <div className="col-span-12 xl:col-span-5">
          <RevenueChart />
        </div>
        <div className="col-span-12 xl:col-span-7">
          <RecentClientsTable />
        </div>
        <div className="col-span-12 xl:col-span-5">
          <LowStockTable />
        </div>
      </div>
    </div>
  );
}
