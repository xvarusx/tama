import Badge from "@/components/ui/badge/Badge";
import { ArrowDownIcon, ArrowUpIcon } from "@/icons";

type MetricCardProps = {
  label: string;
  value: string;
  change?: string;
  trend?: "up" | "down";
  icon: React.ReactNode;
};

export default function MetricCard({
  label,
  value,
  change,
  trend = "up",
  icon,
}: MetricCardProps) {
  return (
    <div className="rounded-2xl border border-gray-200 bg-white p-5 md:p-6 dark:border-gray-800 dark:bg-white/3">
      <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-gray-100 dark:bg-gray-800">
        {icon}
      </div>

      <div className="mt-5 flex items-end justify-between gap-3">
        <div>
          <span className="text-sm text-gray-500 dark:text-gray-400">
            {label}
          </span>
          <h4 className="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">
            {value}
          </h4>
        </div>
        {change && (
          <Badge color={trend === "up" ? "success" : "error"}>
            {trend === "up" ? <ArrowUpIcon /> : <ArrowDownIcon />}
            {change}
          </Badge>
        )}
      </div>
    </div>
  );
}
