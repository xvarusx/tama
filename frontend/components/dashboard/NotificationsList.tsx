"use client";

import { useNotifications } from "@/context/NotificationsContext";
import { BellIcon, BoxIcon, GroupIcon } from "@/icons";
import { getUnreadCount, type DashboardNotification } from "@/lib/dashboard-data";
import Link from "next/link";

const typeStyles: Record<
  DashboardNotification["type"],
  { icon: React.ReactNode; className: string }
> = {
  stock: {
    icon: <BoxIcon className="size-5" />,
    className: "bg-warning-50 text-warning-600 dark:bg-warning-500/15",
  },
  order: {
    icon: <BellIcon className="size-5" />,
    className: "bg-brand-50 text-brand-500 dark:bg-brand-500/15",
  },
  client: {
    icon: <GroupIcon className="size-5" />,
    className: "bg-success-50 text-success-600 dark:bg-success-500/15",
  },
  system: {
    icon: <BellIcon className="size-5" />,
    className: "bg-gray-100 text-gray-500 dark:bg-white/5 dark:text-gray-400",
  },
};

export default function NotificationsList() {
  const { items, markAllRead } = useNotifications();
  const unreadCount = getUnreadCount(items);

  return (
    <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/3">
      <div className="flex flex-col gap-3 border-b border-gray-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6 dark:border-gray-800">
        <div>
          <h3 className="text-lg font-semibold text-gray-800 dark:text-white/90">
            All notifications
          </h3>
          <p className="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">
            {unreadCount} unread
          </p>
        </div>
        <button
          type="button"
          onClick={markAllRead}
          disabled={unreadCount === 0}
          className="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700"
        >
          Mark all as read
        </button>
      </div>

      {items.length === 0 ? (
        <p className="px-6 py-10 text-center text-theme-sm text-gray-500 dark:text-gray-400">
          You&apos;re all caught up.
        </p>
      ) : (
        <ul className="divide-y divide-gray-100 dark:divide-gray-800">
          {items.map((notification) => {
            const style = typeStyles[notification.type];
            const content = (
              <>
                <span
                  className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-full ${style.className}`}
                >
                  {style.icon}
                </span>
                <span className="min-w-0 flex-1">
                  <span className="flex items-start justify-between gap-3">
                    <span
                      className={`text-theme-sm text-gray-800 dark:text-gray-300 ${
                        notification.read ? "" : "font-semibold"
                      }`}
                    >
                      {notification.title}
                    </span>
                    {!notification.read && (
                      <span
                        className="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-brand-500"
                        aria-label="Unread"
                      />
                    )}
                  </span>
                  <span className="mt-1 block text-theme-sm text-gray-500 dark:text-gray-400">
                    {notification.message}
                  </span>
                  <span className="mt-1.5 block text-theme-xs text-gray-400 dark:text-gray-500">
                    {notification.time}
                  </span>
                </span>
              </>
            );

            return (
              <li key={notification.id}>
                {notification.href ? (
                  <Link
                    href={notification.href}
                    className="flex gap-4 px-5 py-4 transition-colors hover:bg-gray-50 sm:px-6 dark:hover:bg-white/3"
                  >
                    {content}
                  </Link>
                ) : (
                  <div className="flex gap-4 px-5 py-4 sm:px-6">
                    {content}
                  </div>
                )}
              </li>
            );
          })}
        </ul>
      )}
    </div>
  );
}
