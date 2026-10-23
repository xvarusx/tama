import PageBreadcrumb from "@/components/common/PageBreadCrumb";
import ProductsTable from "@/components/dashboard/ProductsTable";

export const metadata = {
  title: "Products | ERP",
};

export default function ProductsPage() {
  return (
    <div>
      <PageBreadcrumb pageTitle="Products" />
      <ProductsTable />
    </div>
  );
}
