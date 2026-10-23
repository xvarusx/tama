import DashboardShell from "@/components/layout/DashboardShell";
import { getCurrentUser } from "@/lib/auth/dal";

export const instant = false;

export default async function DashboardLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const user = await getCurrentUser();

  return <DashboardShell email={user.email}>{children}</DashboardShell>;
}
