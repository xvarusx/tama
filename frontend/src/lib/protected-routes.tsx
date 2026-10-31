import { Navigate, Outlet, useLocation } from "react-router-dom";
import { AuthStatusScreen } from "@/components/auth/auth-status-screen";
import { useAuth } from "@/lib/auth-context";

export default function ProtectedRoute() {
  const { status } = useAuth();
  const location = useLocation();

  if (status === "checking" || status === "error") {
    return <AuthStatusScreen />;
  }

  if (status === "anonymous") {
    return <Navigate to="/" replace state={{ from: location }} />;
  }

  return <Outlet />;
}
