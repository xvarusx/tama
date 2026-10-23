import MetricCard from "@/components/dashboard/MetricCard";
import { BoxIcon, BoxIconLine } from "@/icons";
import { computeInventorySummary, inventory } from "@/lib/dashboard-data";

export default function InventorySummary() {
  const summary = computeInventorySummary(inventory);

  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6 xl:grid-cols-3">
      <MetricCard
        label="Total SKUs"
        value={String(summary.totalSkus)}
        icon={<BoxIcon className="size-6 text-gray-800 dark:text-white/90" />}
      />
      <MetricCard
        label="Low stock"
        value={String(summary.lowStock)}
        change="Reorder"
        trend="down"
        icon={
          <BoxIconLine className="size-6 text-gray-800 dark:text-white/90" />
        }
      />
      <MetricCard
        label="Out of stock"
        value={String(summary.outOfStock)}
        change="Restock"
        trend="down"
        icon={<BoxIcon className="size-6 text-gray-800 dark:text-white/90" />}
      />
    </div>
  );
}
