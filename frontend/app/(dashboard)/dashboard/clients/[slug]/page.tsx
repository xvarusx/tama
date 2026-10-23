import PageBreadcrumb from "@/components/common/PageBreadCrumb";
import Badge from "@/components/ui/badge/Badge";
import { ChevronLeftIcon } from "@/icons";
import { clients } from "@/lib/dashboard-data";
import Link from "next/link";
import { notFound } from "next/navigation";

const currency = new Intl.NumberFormat("en-US", {
  style: "currency",
  currency: "USD",
  maximumFractionDigits: 0,
});

export async function generateMetadata({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;
  const client = clients.find((item) => item.slug === slug);
  return { title: client ? `${client.name} | ERP` : "Client not found | ERP" };
}

export default async function ClientDetailPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;
  const client = clients.find((item) => item.slug === slug);

  if (!client) notFound();

  return (
    <div>
      <PageBreadcrumb pageTitle="Client detail" />

      <Link
        href="/dashboard/clients"
        className="mb-4 inline-flex items-center gap-1 text-sm font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
      >
        <ChevronLeftIcon className="size-4" />
        Back to clients
      </Link>

      <div className="rounded-2xl border border-gray-200 bg-white p-5 md:p-6 dark:border-gray-800 dark:bg-white/3">
        <div className="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
          <div className="flex items-center gap-4">
            <span className="flex h-14 w-14 items-center justify-center rounded-full bg-brand-50 text-xl font-semibold text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">
              {client.name.charAt(0)}
            </span>
            <div>
              <h1 className="text-xl font-semibold text-gray-800 dark:text-white/90">
                {client.name}
              </h1>
              <p className="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">
                {client.company} · {client.city}, {client.country}
              </p>
            </div>
          </div>
          <Badge color={client.status === "active" ? "success" : "light"}>
            {client.status === "active" ? "Active" : "Inactive"}
          </Badge>
        </div>

        <dl className="mt-8 grid grid-cols-2 gap-4 border-t border-gray-100 pt-6 sm:grid-cols-4 dark:border-gray-800">
          <div>
            <dt className="text-theme-xs text-gray-500 dark:text-gray-400">
              Orders
            </dt>
            <dd className="mt-1 text-lg font-semibold text-gray-800 dark:text-white/90">
              {client.orders}
            </dd>
          </div>
          <div>
            <dt className="text-theme-xs text-gray-500 dark:text-gray-400">
              Total spent
            </dt>
            <dd className="mt-1 text-lg font-semibold text-gray-800 dark:text-white/90">
              {currency.format(client.totalSpent)}
            </dd>
          </div>
          <div>
            <dt className="text-theme-xs text-gray-500 dark:text-gray-400">
              Client since
            </dt>
            <dd className="mt-1 text-lg font-semibold text-gray-800 dark:text-white/90">
              {client.since}
            </dd>
          </div>
          <div>
            <dt className="text-theme-xs text-gray-500 dark:text-gray-400">
              Status
            </dt>
            <dd className="mt-1 text-lg font-semibold text-gray-800 dark:text-white/90">
              {client.status === "active" ? "Active" : "Inactive"}
            </dd>
          </div>
        </dl>
      </div>

      <div className="mt-4 grid grid-cols-12 gap-4 md:mt-6 md:gap-6">
        <div className="col-span-12 rounded-2xl border border-gray-200 bg-white p-5 md:p-6 xl:col-span-5 dark:border-gray-800 dark:bg-white/3">
          <h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">
            Contact
          </h2>
          <dl className="mt-4 space-y-4">
            <div>
              <dt className="text-theme-xs text-gray-500 dark:text-gray-400">
                Email
              </dt>
              <dd className="mt-1 text-theme-sm text-gray-800 dark:text-gray-300">
                <a
                  href={`mailto:${client.email}`}
                  className="hover:text-brand-500"
                >
                  {client.email}
                </a>
              </dd>
            </div>
            <div>
              <dt className="text-theme-xs text-gray-500 dark:text-gray-400">
                Phone
              </dt>
              <dd className="mt-1 text-theme-sm text-gray-800 dark:text-gray-300">
                <a
                  href={`tel:${client.phone.replace(/[^+\d]/g, "")}`}
                  className="hover:text-brand-500"
                >
                  {client.phone}
                </a>
              </dd>
            </div>
            <div>
              <dt className="text-theme-xs text-gray-500 dark:text-gray-400">
                Location
              </dt>
              <dd className="mt-1 text-theme-sm text-gray-800 dark:text-gray-300">
                {client.city}, {client.country}
              </dd>
            </div>
          </dl>
        </div>

        <div className="col-span-12 rounded-2xl border border-gray-200 bg-white p-5 md:p-6 xl:col-span-7 dark:border-gray-800 dark:bg-white/3">
          <h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">
            Recent activity
          </h2>
          <ul className="mt-4 space-y-4">
            {client.recentActivity.map((activity) => (
              <li
                key={`${activity.date}-${activity.description}`}
                className="flex gap-3"
              >
                <span className="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-brand-500" />
                <div>
                  <p className="text-theme-sm text-gray-800 dark:text-gray-300">
                    {activity.description}
                  </p>
                  <p className="mt-0.5 text-theme-xs text-gray-500 dark:text-gray-400">
                    {activity.date}
                  </p>
                </div>
              </li>
            ))}
          </ul>
        </div>
      </div>
    </div>
  );
}
