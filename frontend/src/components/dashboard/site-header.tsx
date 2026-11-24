import { Link, useLocation } from "react-router-dom"
import { SidebarIcon } from "lucide-react"

import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/components/ui/breadcrumb"
import { Button } from "@/components/ui/button"
import { Separator } from "@/components/ui/separator"
import { useSidebar } from "@/components/ui/sidebar"
import { NotificationBell } from "@/components/notifications/notification-bell"

const sectionLabels: Record<string, string> = {
  "/dashboard/contacts": "Contacts",
  "/dashboard/products": "Products",
  "/dashboard/notifications": "Notifications",
  "/dashboard/notifications/preferences": "Notification preferences",
  "/dashboard/notifications/admin": "Notification admin",
}

export function SiteHeader() {
  const { toggleSidebar } = useSidebar()
  const { pathname } = useLocation()
  const section =
    sectionLabels[pathname] ??
    (pathname.startsWith("/dashboard/products/") ? "Products" : undefined) ??
    (pathname.startsWith("/dashboard/notifications/") ? "Notifications" : undefined)

  return (
    <header className="sticky top-0 z-50 flex w-full items-center border-b bg-background">
      <div className="flex h-(--header-height) w-full items-center gap-2 px-4">
        <Button
          className="h-8 w-8"
          variant="ghost"
          size="icon"
          onClick={toggleSidebar}
        >
          <SidebarIcon />
        </Button>
        <Separator orientation="vertical" className="mr-2 h-4" />
        <Breadcrumb className="hidden sm:block">
          <BreadcrumbList>
            <BreadcrumbItem>
              <BreadcrumbLink render={<Link to="/dashboard" />}>
                Dashboard
              </BreadcrumbLink>
            </BreadcrumbItem>
            {section && (
              <>
                <BreadcrumbSeparator />
                <BreadcrumbItem>
                  <BreadcrumbPage>{section}</BreadcrumbPage>
                </BreadcrumbItem>
              </>
            )}
          </BreadcrumbList>
        </Breadcrumb>
        <div className="ml-auto">
          <NotificationBell />
        </div>
      </div>
    </header>
  )
}
