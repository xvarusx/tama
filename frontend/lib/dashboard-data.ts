export type DashboardClient = {
  id: number;
  slug: string;
  name: string;
  company: string;
  email: string;
  phone: string;
  status: "active" | "inactive";
  since: string;
  city: string;
  country: string;
  orders: number;
  totalSpent: number;
  recentActivity: { date: string; description: string }[];
};

export type DashboardProduct = {
  id: number;
  name: string;
  sku: string;
  category: string;
  price: number;
  stock: number;
  status: "active" | "draft" | "archived";
};

export type DashboardInventoryItem = {
  id: number;
  sku: string;
  product: string;
  warehouse: string;
  quantity: number;
  reorderLevel: number;
};

export type DashboardNotification = {
  id: number;
  title: string;
  message: string;
  type: "order" | "stock" | "client" | "system";
  time: string;
  read: boolean;
  href?: string;
};

export const clients: DashboardClient[] = [
  {
    id: 1,
    slug: "acme-corporation",
    name: "Acme Corporation",
    company: "Acme Corporation",
    email: "finance@acme.example",
    phone: "+1 (555) 010-1000",
    status: "active",
    since: "Jan 12, 2024",
    city: "Springfield",
    country: "United States",
    orders: 42,
    totalSpent: 28450,
    recentActivity: [
      { date: "Oct 21, 2026", description: "Placed order #1042 for 6 POS terminals." },
      { date: "Oct 14, 2026", description: "Updated billing address." },
      { date: "Sep 30, 2026", description: "Paid invoice INV-2031 in full." },
    ],
  },
  {
    id: 2,
    slug: "northwind-traders",
    name: "Northwind Traders",
    company: "Northwind Traders Ltd.",
    email: "ops@northwind.example",
    phone: "+1 (555) 010-2040",
    status: "active",
    since: "Mar 4, 2024",
    city: "Seattle",
    country: "United States",
    orders: 27,
    totalSpent: 16220,
    recentActivity: [
      { date: "Oct 20, 2026", description: "Requested a quote for barcode scanners." },
      { date: "Oct 02, 2026", description: "Renewed annual support plan." },
    ],
  },
  {
    id: 3,
    slug: "globex",
    name: "Globex",
    company: "Globex Industries",
    email: "purchasing@globex.example",
    phone: "+1 (555) 010-3080",
    status: "active",
    since: "Jun 18, 2024",
    city: "Chicago",
    country: "United States",
    orders: 18,
    totalSpent: 9875,
    recentActivity: [
      { date: "Oct 18, 2026", description: "Added a second warehouse to their account." },
      { date: "Sep 22, 2026", description: "Reported a damaged receipt printer." },
    ],
  },
  {
    id: 4,
    slug: "initech",
    name: "Initech",
    company: "Initech Systems",
    email: "accounts@initech.example",
    phone: "+1 (555) 010-4720",
    status: "inactive",
    since: "Aug 9, 2023",
    city: "Austin",
    country: "United States",
    orders: 9,
    totalSpent: 4310,
    recentActivity: [
      { date: "Aug 11, 2026", description: "Account marked inactive after contract ended." },
      { date: "Jul 27, 2026", description: "Returned two card readers." },
    ],
  },
  {
    id: 5,
    slug: "umbrella-health",
    name: "Umbrella Health",
    company: "Umbrella Health Group",
    email: "supply@umbrella.example",
    phone: "+1 (555) 010-5510",
    status: "active",
    since: "Feb 21, 2025",
    city: "Raccoon City",
    country: "United States",
    orders: 33,
    totalSpent: 21780,
    recentActivity: [
      { date: "Oct 19, 2026", description: "Ordered 12 inventory tablets." },
      { date: "Oct 08, 2026", description: "Updated tax exemption certificate." },
      { date: "Sep 14, 2026", description: "Added three new clinic locations." },
    ],
  },
  {
    id: 6,
    slug: "stark-industries",
    name: "Stark Industries",
    company: "Stark Industries Inc.",
    email: "procurement@stark.example",
    phone: "+1 (555) 010-6000",
    status: "active",
    since: "Nov 2, 2025",
    city: "Los Angeles",
    country: "United States",
    orders: 12,
    totalSpent: 7360,
    recentActivity: [
      { date: "Oct 17, 2026", description: "Requested net-60 payment terms." },
      { date: "Sep 05, 2026", description: "Placed first order for label printers." },
    ],
  },
];

export const products: DashboardProduct[] = [
  {
    id: 1,
    name: "POS Terminal",
    sku: "POS-1001",
    category: "Hardware",
    price: 899,
    stock: 24,
    status: "active",
  },
  {
    id: 2,
    name: "Barcode Scanner",
    sku: "SCN-2040",
    category: "Hardware",
    price: 149,
    stock: 61,
    status: "active",
  },
  {
    id: 3,
    name: "Receipt Printer",
    sku: "PRN-3300",
    category: "Hardware",
    price: 279,
    stock: 8,
    status: "active",
  },
  {
    id: 4,
    name: "Cash Drawer",
    sku: "DRW-1100",
    category: "Hardware",
    price: 129,
    stock: 0,
    status: "active",
  },
  {
    id: 5,
    name: "Inventory Tablet",
    sku: "TAB-5000",
    category: "Devices",
    price: 499,
    stock: 17,
    status: "active",
  },
  {
    id: 6,
    name: "Card Reader",
    sku: "CRD-7700",
    category: "Payments",
    price: 89,
    stock: 43,
    status: "active",
  },
  {
    id: 7,
    name: "Label Printer",
    sku: "LBL-2200",
    category: "Hardware",
    price: 319,
    stock: 12,
    status: "draft",
  },
  {
    id: 8,
    name: "Thermal Paper Rolls",
    sku: "PAP-0100",
    category: "Consumables",
    price: 24,
    stock: 120,
    status: "archived",
  },
];

export const inventory: DashboardInventoryItem[] = [
  {
    id: 1,
    sku: "POS-1001",
    product: "POS Terminal",
    warehouse: "Main Warehouse",
    quantity: 24,
    reorderLevel: 10,
  },
  {
    id: 2,
    sku: "SCN-2040",
    product: "Barcode Scanner",
    warehouse: "Main Warehouse",
    quantity: 61,
    reorderLevel: 20,
  },
  {
    id: 3,
    sku: "PRN-3300",
    product: "Receipt Printer",
    warehouse: "West Depot",
    quantity: 8,
    reorderLevel: 15,
  },
  {
    id: 4,
    sku: "DRW-1100",
    product: "Cash Drawer",
    warehouse: "West Depot",
    quantity: 0,
    reorderLevel: 10,
  },
  {
    id: 5,
    sku: "TAB-5000",
    product: "Inventory Tablet",
    warehouse: "Main Warehouse",
    quantity: 17,
    reorderLevel: 8,
  },
  {
    id: 6,
    sku: "CRD-7700",
    product: "Card Reader",
    warehouse: "Main Warehouse",
    quantity: 43,
    reorderLevel: 15,
  },
  {
    id: 7,
    sku: "LBL-2200",
    product: "Label Printer",
    warehouse: "East Depot",
    quantity: 12,
    reorderLevel: 12,
  },
  {
    id: 8,
    sku: "PAP-0100",
    product: "Thermal Paper Rolls",
    warehouse: "East Depot",
    quantity: 120,
    reorderLevel: 40,
  },
];

export const notifications: DashboardNotification[] = [
  {
    id: 1,
    title: "Low stock on Receipt Printer",
    message: "Only 8 units remain in the West Depot, below the reorder level.",
    type: "stock",
    time: "5 min ago",
    read: false,
    href: "/dashboard/inventory",
  },
  {
    id: 2,
    title: "New order from Acme Corporation",
    message: "Order #1042 for six POS terminals was placed.",
    type: "order",
    time: "32 min ago",
    read: false,
    href: "/dashboard/clients/acme-corporation",
  },
  {
    id: 3,
    title: "Cash Drawer is out of stock",
    message: "The West Depot has no remaining units. Reorder to avoid delays.",
    type: "stock",
    time: "1 hour ago",
    read: false,
    href: "/dashboard/inventory",
  },
  {
    id: 4,
    title: "Northwind Traders requested a quote",
    message: "The customer asked for pricing on 20 barcode scanners.",
    type: "client",
    time: "3 hours ago",
    read: true,
    href: "/dashboard/clients/northwind-traders",
  },
  {
    id: 5,
    title: "Inventory sync completed",
    message: "All eight stock items were synchronized successfully.",
    type: "system",
    time: "Yesterday",
    read: true,
  },
  {
    id: 6,
    title: "Globex added a warehouse",
    message: "A second warehouse was linked to the Globex account.",
    type: "client",
    time: "2 days ago",
    read: true,
    href: "/dashboard/clients/globex",
  },
];

export function filterByQuery<T>(
  items: T[],
  query: string,
  fields: (item: T) => string[],
): T[] {
  const needle = query.trim().toLowerCase();
  if (needle === "") return items;

  return items.filter((item) =>
    fields(item).some((field) => field.toLowerCase().includes(needle)),
  );
}

export function paginate<T>(
  items: T[],
  page: number,
  pageSize: number,
): { items: T[]; totalPages: number; page: number } {
  const totalPages = Math.max(1, Math.ceil(items.length / pageSize));
  const safePage = Math.min(Math.max(1, Math.trunc(page)), totalPages);
  const start = (safePage - 1) * pageSize;

  return {
    items: items.slice(start, start + pageSize),
    totalPages,
    page: safePage,
  };
}

export function getInventoryStatus(
  item: DashboardInventoryItem,
): "out" | "low" | "ok" {
  if (item.quantity === 0) return "out";
  if (item.quantity <= item.reorderLevel) return "low";
  return "ok";
}

export function computeInventorySummary(items: DashboardInventoryItem[]): {
  totalSkus: number;
  lowStock: number;
  outOfStock: number;
  totalUnits: number;
} {
  return items.reduce(
    (summary, item) => {
      const status = getInventoryStatus(item);
      return {
        totalSkus: summary.totalSkus + 1,
        lowStock: summary.lowStock + (status === "low" ? 1 : 0),
        outOfStock: summary.outOfStock + (status === "out" ? 1 : 0),
        totalUnits: summary.totalUnits + item.quantity,
      };
    },
    { totalSkus: 0, lowStock: 0, outOfStock: 0, totalUnits: 0 },
  );
}

export function getUnreadCount(items: DashboardNotification[]): number {
  return items.filter((item) => !item.read).length;
}
