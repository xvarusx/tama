import PageBreadcrumb from "@/components/common/PageBreadCrumb";
import ProductsTable from "@/components/dashboard/ProductsTable";
import { getCurrentUser } from "@/lib/auth/dal";

export const metadata = {
  title: "Products | ERP",
};

export const instant = false;

export default async function ProductsPage() {
  await getCurrentUser();
  return (
    <div>
      <PageBreadcrumb pageTitle="Products" />
      <ProductsTable />
    </div>
  );
}
