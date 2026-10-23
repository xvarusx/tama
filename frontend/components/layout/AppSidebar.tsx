"use client";

import { useSidebar } from "@/context/SidebarContext";
import {
  BellIcon,
  BoxCubeIcon,
  BoxIcon,
  GridIcon,
  HorizontaLDots,
  UserCircleIcon,
} from "@/icons";
import { cn } from "@/utils/cn";
import Image from "next/image";
import Link from "next/link";
import { usePathname } from "next/navigation";

type NavItem = {
  label: string;
  href: string;
  icon: React.ReactNode;
};

const navItems: NavItem[] = [
  { label: "Dashboard", href: "/dashboard", icon: <GridIcon /> },
  { label: "Products", href: "/dashboard/products", icon: <BoxCubeIcon /> },
  { label: "Clients", href: "/dashboard/clients", icon: <UserCircleIcon /> },
  { label: "Inventory", href: "/dashboard/inventory", icon: <BoxIcon /> },
  {
    label: "Notifications",
    href: "/dashboard/notifications",
    icon: <BellIcon />,
  },
];

function isActive(pathname: string, href: string): boolean {
  if (href === "/dashboard") return pathname === href;
  return pathname === href || pathname.startsWith(`${href}/`);
}

const AppSidebar: React.FC = () => {
  const {
    isExpanded,
    isMobileOpen,
    isHovered,
    setIsHovered,
    toggleMobileSidebar,
  } = useSidebar();
  const pathname = usePathname();
  const showLabels = isExpanded || isHovered || isMobileOpen;

  return (
    <aside
      className={cn(
        "fixed top-0 left-0 z-50 flex h-full flex-col border-r border-gray-200 bg-white px-5 text-gray-900 transition-all duration-300 ease-in-out dark:border-gray-800 dark:bg-gray-900",
        isExpanded || isHovered || isMobileOpen ? "w-72.5" : "w-22.5",
        isMobileOpen
          ? "translate-x-0"
          : "-translate-x-full xl:translate-x-0",
      )}
      onMouseEnter={() => !isExpanded && setIsHovered(true)}
      onMouseLeave={() => setIsHovered(false)}
    >
      <div
        className={cn(
          "flex py-8",
          !isExpanded && !isHovered ? "xl:justify-center" : "justify-start",
        )}
      >
        <Link href="/dashboard" aria-label="Dashboard home">
          {showLabels ? (
            <Image
              src="/images/logo/auth-logo.svg"
              alt="ERP"
              width={132}
              height={36}
              priority
              className="h-9 w-auto dark:brightness-0 dark:invert"
            />
          ) : (
            <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-500 text-sm font-semibold text-white">
              ERP
            </span>
          )}
        </Link>
      </div>

      <div className="custom-scrollbar flex flex-col overflow-y-auto duration-300 ease-linear">
        <nav className="mb-6">
          <div className="flex flex-col gap-4">
            <div>
              <h2
                className={cn(
                  "mb-4 flex text-xs leading-5 text-gray-400 uppercase",
                  showLabels ? "justify-start" : "xl:justify-center",
                )}
              >
                {showLabels ? "Menu" : <HorizontaLDots />}
              </h2>
              <ul className="flex flex-col gap-1">
                {navItems.map((item) => {
                  const active = isActive(pathname, item.href);
                  return (
                    <li key={item.href}>
                      <Link
                        href={item.href}
                        onClick={() => {
                          if (isMobileOpen) toggleMobileSidebar();
                        }}
                        className={cn(
                          "group menu-item",
                          active ? "menu-item-active" : "menu-item-inactive",
                          !showLabels ? "xl:justify-center" : "justify-start",
                        )}
                      >
                        <span
                          className={cn(
                            active
                              ? "menu-item-icon-active"
                              : "menu-item-icon-inactive",
                          )}
                        >
                          {item.icon}
                        </span>
                        {showLabels && <span>{item.label}</span>}
                      </Link>
                    </li>
                  );
                })}
              </ul>
            </div>
          </div>
        </nav>
      </div>
    </aside>
  );
};

export default AppSidebar;
