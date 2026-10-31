import { Navigate, Outlet } from "react-router-dom";
import { AuthStatusScreen } from "@/components/auth/auth-status-screen";
import { useAuth } from "@/lib/auth-context";

export default function PublicRoute() {
  const { status } = useAuth();

  if (status === "checking" || status === "error") {
    return <AuthStatusScreen />;
  }

  if (status === "authenticated") {
    return <Navigate to="/dashboard" replace />;
  }

  return <Outlet />;
}
