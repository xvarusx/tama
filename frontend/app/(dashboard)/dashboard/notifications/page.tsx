import PageBreadcrumb from "@/components/common/PageBreadCrumb";
import NotificationsList from "@/components/dashboard/NotificationsList";

export const metadata = {
  title: "Notifications | ERP",
};

export default function NotificationsPage() {
  return (
    <div>
      <PageBreadcrumb pageTitle="Notifications" />
      <NotificationsList />
    </div>
  );
}
