"use client";

import { Dropdown } from "@/components/ui/dropdown/Dropdown";
import { DropdownItem } from "@/components/ui/dropdown/DropdownItem";
import { useNotifications } from "@/context/NotificationsContext";
import { getUnreadCount } from "@/lib/dashboard-data";
import { BellIcon } from "@/icons";
import Link from "next/link";
import { useState } from "react";

const typeColors: Record<string, string> = {
  stock: "bg-warning-500",
  order: "bg-brand-500",
  client: "bg-success-500",
  system: "bg-gray-400",
};

export default function NotificationDropdown() {
  const [isOpen, setIsOpen] = useState(false);
  const { items } = useNotifications();
  const unreadCount = getUnreadCount(items);

  return (
    <div className="relative">
      <button
        type="button"
        onClick={() => setIsOpen((value) => !value)}
        aria-label={`Notifications${unreadCount > 0 ? ` (${unreadCount} unread)` : ""}`}
        className="dropdown-toggle relative flex h-11 w-11 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
      >
        {unreadCount > 0 && (
          <span className="absolute top-0.5 right-0 z-10 flex h-2 w-2 rounded-full bg-orange-400">
            <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-orange-400 opacity-75" />
          </span>
        )}
        <BellIcon className="fill-current" />
      </button>

      <Dropdown
        isOpen={isOpen}
        onClose={() => setIsOpen(false)}
        className="absolute -left-13.5 mt-4.25 flex h-auto w-87.5 flex-col rounded-2xl border border-gray-200 bg-white p-3 shadow-theme-lg sm:w-90.25 xl:right-0 xl:left-auto dark:border-gray-800 dark:bg-gray-dark"
      >
        <div className="mb-3 flex items-center justify-between border-b border-gray-100 pb-3 dark:border-gray-700">
          <h5 className="text-lg font-semibold text-gray-800 dark:text-gray-200">
            Notifications
          </h5>
          <span className="text-xs text-gray-500 dark:text-gray-400">
            {unreadCount} unread
          </span>
        </div>

        <ul className="custom-scrollbar flex flex-col overflow-y-auto">
          {items.slice(0, 3).map((notification) => (
            <li key={notification.id}>
              <DropdownItem
                tag={notification.href ? "a" : "button"}
                href={notification.href}
                onItemClick={() => setIsOpen(false)}
                className="flex gap-3 rounded-lg border-b border-gray-100 px-4.5 py-3 hover:bg-gray-100 dark:border-gray-800 dark:hover:bg-white/5"
              >
                <span className="relative mt-1.5 flex h-2.5 w-2.5 shrink-0 rounded-full">
                  <span
                    className={`h-2.5 w-2.5 rounded-full ${
                      typeColors[notification.type] ?? "bg-gray-400"
                    }`}
                  />
                </span>
                <span className="block">
                  <span className="block text-theme-sm font-medium text-gray-800 dark:text-white/90">
                    {notification.title}
                  </span>
                  <span className="mt-1 block text-theme-xs text-gray-500 dark:text-gray-400">
                    {notification.time}
                  </span>
                </span>
              </DropdownItem>
            </li>
          ))}
        </ul>

        <Link
          href="/dashboard/notifications"
          onClick={() => setIsOpen(false)}
          className="mt-3 block rounded-lg border border-gray-300 bg-white px-4 py-2 text-center text-sm font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700"
        >
          View all notifications
        </Link>
      </Dropdown>
    </div>
  );
}
