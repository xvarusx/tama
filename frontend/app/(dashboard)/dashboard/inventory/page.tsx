import PageBreadcrumb from "@/components/common/PageBreadCrumb";
import InventorySummary from "@/components/dashboard/InventorySummary";
import InventoryTable from "@/components/dashboard/InventoryTable";
import { getCurrentUser } from "@/lib/auth/dal";

export const metadata = {
  title: "Inventory | ERP",
};

export const instant = false;

export default async function InventoryPage() {
  await getCurrentUser();
  return (
    <div>
      <PageBreadcrumb pageTitle="Inventory" />
      <InventorySummary />
      <InventoryTable />
    </div>
  );
}
