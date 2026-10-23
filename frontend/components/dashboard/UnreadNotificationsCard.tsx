"use client";

import MetricCard from "@/components/dashboard/MetricCard";
import { useNotifications } from "@/context/NotificationsContext";
import { BellIcon } from "@/icons";
import { getUnreadCount } from "@/lib/dashboard-data";

export default function UnreadNotificationsCard() {
  const { items } = useNotifications();

  return (
    <MetricCard
      label="Unread notifications"
      value={String(getUnreadCount(items))}
      icon={<BellIcon className="size-6 text-gray-800 dark:text-white/90" />}
    />
  );
}
