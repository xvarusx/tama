import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import { BrowserRouter, Routes, Route, Navigate } from "react-router-dom";
import { ThemeProvider } from "@/components/themes/theme-provider"
import { ModeToggle } from "@/components/themes/mode-toggle"
import { Toaster } from "@/components/ui/toast"
import App from "./App.tsx";
import SignupPage from "@/pages/(auth)/sign-up/page";
import DashboardLayout from "@/pages/dashboard/layout";
import ContactsPage from "@/pages/dashboard/contacts/page";
import ProductsPage from "@/pages/dashboard/products/page";
import ProtectedRoute from "@/lib/protected-routes";
import PublicRoute from "@/lib/public-routes";
import { AuthProvider } from "@/lib/auth-provider";
import "./index.css";

createRoot(document.getElementById("root")!).render(
  <StrictMode>
    <ThemeProvider defaultTheme="dark" storageKey="vite-ui-theme">
    <AuthProvider>
    <BrowserRouter>
      <ModeToggle />
      <Toaster />
      <Routes>
          {/* public routes */}
        <Route element={<PublicRoute />}>
          <Route path="/" element={<App />} />
          <Route path="/signup" element={<SignupPage />} />
        </Route>
          {/* private routes */}
        <Route element={<ProtectedRoute />}>
          <Route path="/dashboard" element={<DashboardLayout />}>
            <Route index element={<Navigate to="contacts" replace />} />
            <Route path="contacts" element={<ContactsPage />} />
            <Route path="products" element={<ProductsPage />} />
          </Route>
        </Route>
      </Routes>
    </BrowserRouter>
    </AuthProvider>
    </ThemeProvider>
  </StrictMode>,
);
