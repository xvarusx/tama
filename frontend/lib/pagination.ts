export type PaginationItem = number | "ellipsis";

export function getPaginationItems(
  currentPage: number,
  totalPages: number,
): PaginationItem[] {
  if (totalPages <= 1) return [];

  const current = Math.min(
    Math.max(Number.isFinite(currentPage) ? Math.trunc(currentPage) : 1, 1),
    totalPages,
  );

  if (totalPages <= 5) {
    return Array.from({ length: totalPages }, (_, index) => index + 1);
  }

  const visible = new Set<number>([1, totalPages]);

  if (current <= 3) {
    for (const page of [2, 3, 4]) visible.add(page);
  } else if (current >= totalPages - 2) {
    for (const page of [totalPages - 3, totalPages - 2, totalPages - 1]) {
      visible.add(page);
    }
  } else {
    for (const page of [current - 1, current, current + 1]) visible.add(page);
  }

  const pages = [...visible].sort((a, b) => a - b);
  const items: PaginationItem[] = [];

  pages.forEach((page, index) => {
    if (index > 0 && page - pages[index - 1] > 1) items.push("ellipsis");
    items.push(page);
  });

  return items;
}
