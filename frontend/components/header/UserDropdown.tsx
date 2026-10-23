"use client";

import LogoutButton from "@/components/auth/LogoutButton";
import { Dropdown } from "@/components/ui/dropdown/Dropdown";
import { DropdownItem } from "@/components/ui/dropdown/DropdownItem";
import { ChevronDownIcon, UserCircleIcon } from "@/icons";
import Link from "next/link";
import { useState } from "react";

const itemClasses =
  "group flex items-center gap-3 rounded-lg px-3 py-2 text-theme-sm font-medium text-gray-700 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300";

export default function UserDropdown({ email }: { email: string }) {
  const [isOpen, setIsOpen] = useState(false);
  const initial = email.charAt(0).toUpperCase() || "U";

  return (
    <div className="relative">
      <button
        type="button"
        onClick={() => setIsOpen((value) => !value)}
        className="dropdown-toggle flex items-center text-gray-700 dark:text-gray-400"
        aria-label="Open user menu"
      >
        <span className="me-3 flex h-11 w-11 items-center justify-center rounded-full bg-brand-50 text-sm font-semibold text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">
          {initial}
        </span>
        <span className="me-1 hidden max-w-40 truncate text-theme-sm font-medium sm:block">
          {email}
        </span>
        <ChevronDownIcon
          className={`size-5 text-gray-500 transition-transform duration-200 dark:text-gray-400 ${
            isOpen ? "rotate-180" : ""
          }`}
        />
      </button>

      <Dropdown
        isOpen={isOpen}
        onClose={() => setIsOpen(false)}
        className="absolute mt-4.25 flex w-65 flex-col rounded-2xl border border-gray-200 bg-white p-3 shadow-theme-lg ltr:right-0 rtl:right-auto rtl:left-0 dark:border-gray-800 dark:bg-gray-dark"
      >
        <div className="border-b border-gray-200 pb-3 dark:border-gray-800">
          <span className="block text-theme-sm font-medium text-gray-700 dark:text-gray-400">
            Signed in as
          </span>
          <span className="mt-0.5 block truncate text-theme-xs text-gray-500 dark:text-gray-400">
            {email}
          </span>
        </div>

        <ul className="flex flex-col gap-1 pt-3">
          <li>
            <DropdownItem
              tag="a"
              href="/dashboard"
              onItemClick={() => setIsOpen(false)}
              baseClassName=""
              className={itemClasses}
            >
              <UserCircleIcon className="size-5" />
              Dashboard
            </DropdownItem>
          </li>
          <li>
            <LogoutButton
              className={`${itemClasses} w-full text-start text-error-600 hover:bg-error-50 dark:text-error-400 dark:hover:bg-error-500/10`}
            />
          </li>
        </ul>

        <Link
          href="/dashboard/notifications"
          onClick={() => setIsOpen(false)}
          className="mt-3 rounded-lg border border-gray-200 px-3 py-2 text-center text-theme-xs text-gray-500 hover:bg-gray-100 dark:border-gray-800 dark:text-gray-400 dark:hover:bg-white/5"
        >
          View notifications
        </Link>
      </Dropdown>
    </div>
  );
}
