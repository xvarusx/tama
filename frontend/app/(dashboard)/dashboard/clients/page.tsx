import PageBreadcrumb from "@/components/common/PageBreadCrumb";
import ClientsTable from "@/components/dashboard/ClientsTable";
import { getCurrentUser } from "@/lib/auth/dal";

export const metadata = {
  title: "Clients | ERP",
};

export const instant = false;

export default async function ClientsPage() {
  await getCurrentUser();
  return (
    <div>
      <PageBreadcrumb pageTitle="Clients" />
      <ClientsTable />
    </div>
  );
}
