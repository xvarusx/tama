"use client";

import type { ApexOptions } from "apexcharts";
import dynamic from "next/dynamic";

const ReactApexChart = dynamic(() => import("react-apexcharts"), {
  ssr: false,
});

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
  colors: ["#465fff"],
  chart: {
    fontFamily: "Outfit, sans-serif",
    type: "bar",
    height: 220,
    toolbar: { show: false },
  },
  plotOptions: {
    bar: {
      horizontal: false,
      columnWidth: "39%",
      borderRadius: 5,
      borderRadiusApplication: "end",
    },
  },
  dataLabels: { enabled: false },
  stroke: { show: true, width: 4, colors: ["transparent"] },
  xaxis: {
    categories: months,
    axisBorder: { show: false },
    axisTicks: { show: false },
  },
  legend: {
    show: true,
    position: "top",
    horizontalAlign: "left",
    fontFamily: "Outfit",
  },
  grid: { yaxis: { lines: { show: true } } },
  fill: { opacity: 1 },
  tooltip: { y: { formatter: (value: number) => `${value} orders` } },
};

const series = [
  {
    name: "Orders",
    data: [32, 41, 38, 54, 61, 58, 69, 74, 66, 82, 91, 96],
  },
];

export default function OrdersChart() {
  return (
    <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white px-5 pt-5 sm:px-6 sm:pt-6 dark:border-gray-800 dark:bg-white/3">
      <h3 className="text-lg font-semibold text-gray-800 dark:text-white/90">
        Orders by month
      </h3>
      <p className="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">
        Total orders placed across all clients this year.
      </p>

      <div className="custom-scrollbar mt-4 max-w-full overflow-x-auto">
        <div className="min-w-162.5 pl-2 xl:min-w-full">
          <ReactApexChart
            options={options}
            series={series}
            type="bar"
            height={220}
          />
        </div>
      </div>
    </div>
  );
}
