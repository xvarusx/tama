import { useState } from "react"
import { Link, useLocation } from "react-router-dom"
import { ChevronRight, type LucideIcon } from "lucide-react"

import {
  SidebarGroup,
  SidebarGroupLabel,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarMenuSub,
  SidebarMenuSubButton,
  SidebarMenuSubItem,
} from "@/components/ui/sidebar"

export function NavMain({
  items,
}: {
  items: {
    title: string
    url: string
    icon: LucideIcon
    disabled?: boolean
    items?: { title: string; url: string }[]
  }[]
}) {
  const { pathname } = useLocation()
  const [openItems, setOpenItems] = useState<Record<string, boolean>>({})

  return (
    <SidebarGroup>
      <SidebarGroupLabel>Platform</SidebarGroupLabel>
      <SidebarMenu>
        {items.map((item) => {
          const children = item.items ?? []
          const hasChildren = children.length > 0
          const isParentActive =
            !item.disabled &&
            (pathname === item.url || pathname.startsWith(`${item.url}/`))
          const isOpen = openItems[item.title] ?? (isParentActive && hasChildren)

          if (!hasChildren) {
            return (
              <SidebarMenuItem key={item.title}>
                <SidebarMenuButton
                  tooltip={item.title}
                  isActive={!item.disabled && pathname === item.url}
                  disabled={item.disabled}
                  {...(!item.disabled ? { render: <Link to={item.url} /> } : {})}
                >
                  <item.icon />
                  <span>{item.title}</span>
                </SidebarMenuButton>
              </SidebarMenuItem>
            )
          }

          return (
            <SidebarMenuItem key={item.title}>
              <div className="flex items-center">
                <SidebarMenuButton
                  tooltip={item.title}
                  isActive={isParentActive}
                  disabled={item.disabled}
                  className="flex-1"
                  {...(!item.disabled ? { render: <Link to={item.url} /> } : {})}
                >
                  <item.icon />
                  <span>{item.title}</span>
                </SidebarMenuButton>
                <button
                  type="button"
                  aria-label={`Toggle ${item.title} submenu`}
                  aria-expanded={isOpen}
                  disabled={item.disabled}
                  onClick={() =>
                    setOpenItems((current) => ({
                      ...current,
                      [item.title]: !isOpen,
                    }))
                  }
                  className="flex size-7 shrink-0 items-center justify-center rounded-sm text-sidebar-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground focus-visible:ring-2 focus-visible:ring-sidebar-ring outline-hidden disabled:pointer-events-none disabled:opacity-50"
                >
                  <ChevronRight
                    className={`size-4 transition-transform${isOpen ? " rotate-90" : ""}`}
                  />
                </button>
              </div>
              {isOpen && (
                <SidebarMenuSub>
                  {children.map((child) => (
                    <SidebarMenuSubItem key={child.title}>
                      <SidebarMenuSubButton
                        isActive={pathname === child.url}
                        render={<Link to={child.url} />}
                      >
                        <span>{child.title}</span>
                      </SidebarMenuSubButton>
                    </SidebarMenuSubItem>
                  ))}
                </SidebarMenuSub>
              )}
            </SidebarMenuItem>
          )
        })}
      </SidebarMenu>
    </SidebarGroup>
  )
}
