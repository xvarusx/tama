"use client";

import {
  notifications as initialNotifications,
  type DashboardNotification,
} from "@/lib/dashboard-data";
import { createContext, useCallback, useContext, useState } from "react";

type NotificationsContextType = {
  items: DashboardNotification[];
  markAllRead: () => void;
};

const NotificationsContext = createContext<
  NotificationsContextType | undefined
>(undefined);

export function useNotifications() {
  const context = useContext(NotificationsContext);
  if (!context) {
    throw new Error("useNotifications must be used within NotificationsProvider");
  }
  return context;
}

export function NotificationsProvider({
  children,
}: {
  children: React.ReactNode;
}) {
  const [items, setItems] = useState<DashboardNotification[]>(
    initialNotifications,
  );

  const markAllRead = useCallback(() => {
    setItems((current) =>
      current.map((notification) => ({ ...notification, read: true })),
    );
  }, []);

  return (
    <NotificationsContext.Provider value={{ items, markAllRead }}>
      {children}
    </NotificationsContext.Provider>
  );
}
