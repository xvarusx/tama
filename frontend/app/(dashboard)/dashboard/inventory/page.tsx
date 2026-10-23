import PageBreadcrumb from "@/components/common/PageBreadCrumb";
import InventorySummary from "@/components/dashboard/InventorySummary";
import InventoryTable from "@/components/dashboard/InventoryTable";

export const metadata = {
  title: "Inventory | ERP",
};

export default function InventoryPage() {
  return (
    <div>
      <PageBreadcrumb pageTitle="Inventory" />
      <InventorySummary />
      <InventoryTable />
    </div>
  );
}
