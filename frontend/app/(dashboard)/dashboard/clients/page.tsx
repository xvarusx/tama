import PageBreadcrumb from "@/components/common/PageBreadCrumb";
import ClientsTable from "@/components/dashboard/ClientsTable";

export const metadata = {
  title: "Clients | ERP",
};

export default function ClientsPage() {
  return (
    <div>
      <PageBreadcrumb pageTitle="Clients" />
      <ClientsTable />
    </div>
  );
}
