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
import ContactShowPage from "@/pages/dashboard/contacts/show";
import ProductsPage from "@/pages/dashboard/products/page";
import ProductShowPage from "@/pages/dashboard/products/show";
import InventoryPage from "@/pages/dashboard/inventory/page";
import InventoryShowPage from "@/pages/dashboard/inventory/show";
import InvoicesPage from "@/pages/dashboard/invoices/page";
import InvoiceShowPage from "@/pages/dashboard/invoices/show";
import PaymentsPage from "@/pages/dashboard/payments/page";
import PaymentShowPage from "@/pages/dashboard/payments/show";
import PaymentPlansPage from "@/pages/dashboard/payment-plans/page";
import PaymentPlanShowPage from "@/pages/dashboard/payment-plans/show";
import InstallmentsPage from "@/pages/dashboard/installments/page";
import InstallmentShowPage from "@/pages/dashboard/installments/show";
import AllocationsPage from "@/pages/dashboard/allocations/page";
import RemindersPage from "@/pages/dashboard/reminders/page";
import ReminderShowPage from "@/pages/dashboard/reminders/show";
import NotificationsPage from "@/pages/dashboard/notifications/page";
import NotificationPreferencesPage from "@/pages/dashboard/notifications/preferences";
import NotificationAdminPage from "@/pages/dashboard/notifications/admin";
import SettingsPage from "@/pages/dashboard/settings/page";
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
            <Route path="contacts/:id" element={<ContactShowPage />} />
            <Route path="products" element={<ProductsPage />} />
            <Route path="products/:id" element={<ProductShowPage />} />
            <Route path="inventory" element={<InventoryPage />} />
            <Route path="inventory/:id" element={<InventoryShowPage />} />
            <Route path="invoices" element={<InvoicesPage />} />
            <Route path="invoices/:id" element={<InvoiceShowPage />} />
            <Route path="payments" element={<PaymentsPage />} />
            <Route path="payments/:id" element={<PaymentShowPage />} />
            <Route path="payment-plans" element={<PaymentPlansPage />} />
            <Route path="payment-plans/:id" element={<PaymentPlanShowPage />} />
            <Route path="installments" element={<InstallmentsPage />} />
            <Route path="installments/:id" element={<InstallmentShowPage />} />
            <Route path="allocations" element={<AllocationsPage />} />
            <Route path="reminders" element={<RemindersPage />} />
            <Route path="reminders/:id" element={<ReminderShowPage />} />
            <Route path="notifications" element={<NotificationsPage />} />
            <Route path="notifications/preferences" element={<NotificationPreferencesPage />} />
            <Route path="notifications/admin" element={<NotificationAdminPage />} />
            <Route path="settings" element={<SettingsPage />} />
          </Route>
        </Route>
      </Routes>
    </BrowserRouter>
    </AuthProvider>
    </ThemeProvider>
  </StrictMode>,
);
