"use client";

import type { ApexOptions } from "apexcharts";
import dynamic from "next/dynamic";

const Chart = dynamic(() => import("react-apexcharts"), { ssr: false });

const months = [
  "Jan",
  "Feb",
  "Mar",
  "Apr",
  "May",
  "Jun",
  "Jul",
  "Aug",
  "Sep",
  "Oct",
  "Nov",
  "Dec",
];

const options: ApexOptions = {
  legend: {
    show: true,
    position: "top",
    horizontalAlign: "left",
  },
  colors: ["#465fff", "#9cb9ff"],
  chart: {
    fontFamily: "Outfit, sans-serif",
    height: 260,
    type: "area",
    toolbar: { show: false },
  },
  stroke: { curve: "smooth", width: [2, 2] },
  fill: {
    type: "gradient",
    gradient: { opacityFrom: 0.55, opacityTo: 0 },
  },
  markers: {
    size: 0,
    strokeColors: "#fff",
    strokeWidth: 2,
    hover: { size: 6 },
  },
  grid: {
    xaxis: { lines: { show: false } },
    yaxis: { lines: { show: true } },
  },
  dataLabels: { enabled: false },
  tooltip: { enabled: true },
  xaxis: {
    type: "category",
    categories: months,
    axisBorder: { show: false },
    axisTicks: { show: false },
    tooltip: { enabled: false },
  },
  yaxis: {
    labels: {
      style: { fontSize: "12px", colors: ["#6B7280"] },
    },
  },
};

const series = [
  {
    name: "Revenue",
    data: [18, 22, 19, 26, 31, 28, 34, 39, 36, 42, 47, 51],
  },
  {
    name: "Orders",
    data: [12, 15, 13, 18, 21, 19, 24, 27, 25, 30, 33, 36],
  },
];

export default function RevenueChart() {
  return (
    <div className="rounded-2xl border border-gray-200 bg-white px-5 pt-5 pb-5 sm:px-6 sm:pt-6 dark:border-gray-800 dark:bg-white/3">
      <div className="mb-5">
        <h3 className="text-lg font-semibold text-gray-800 dark:text-white/90">
          Revenue and orders
        </h3>
        <p className="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">
          Revenue in thousands of dollars against order volume.
        </p>
      </div>

      <div className="custom-scrollbar max-w-full overflow-x-auto">
        <div className="min-w-162.5 xl:min-w-full">
          <Chart options={options} series={series} type="area" height={260} />
        </div>
      </div>
    </div>
  );
}
