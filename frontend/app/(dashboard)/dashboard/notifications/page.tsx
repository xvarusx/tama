import PageBreadcrumb from "@/components/common/PageBreadCrumb";
import NotificationsList from "@/components/dashboard/NotificationsList";
import { getCurrentUser } from "@/lib/auth/dal";

export const metadata = {
  title: "Notifications | ERP",
};

export const instant = false;

export default async function NotificationsPage() {
  await getCurrentUser();
  return (
    <div>
      <PageBreadcrumb pageTitle="Notifications" />
      <NotificationsList />
    </div>
  );
}
