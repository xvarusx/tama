import { getPaginationItems } from "@/lib/pagination";

type PaginationProps = {
  currentPage: number;
  totalPages: number;
  onPageChange: (page: number) => void;
};

const Pagination: React.FC<PaginationProps> = ({
  currentPage,
  totalPages,
  onPageChange,
}) => {
  const items = getPaginationItems(currentPage, totalPages);

  if (items.length === 0) return null;

  return (
    <div className="flex items-center">
      <button
        type="button"
        onClick={() => onPageChange(currentPage - 1)}
        disabled={currentPage === 1}
        className="mr-2.5 flex h-10 items-center justify-center rounded-lg border border-gray-300 bg-white px-3.5 py-2.5 text-sm text-gray-700 shadow-theme-xs hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/3"
      >
        Previous
      </button>
      <div className="flex items-center gap-2">
        {items.map((item, index) =>
          item === "ellipsis" ? (
            <span key={`ellipsis-${index}`} className="px-2">
              ...
            </span>
          ) : (
            <button
              key={item}
              type="button"
              onClick={() => onPageChange(item)}
              className={`flex h-10 w-10 items-center justify-center rounded-lg text-sm font-medium ${
                currentPage === item
                  ? "bg-brand-500 text-white"
                  : "text-gray-700 hover:bg-blue-500/8 hover:text-brand-500 dark:text-gray-400 dark:hover:text-brand-500"
              }`}
            >
              {item}
            </button>
          ),
        )}
      </div>
      <button
        type="button"
        onClick={() => onPageChange(currentPage + 1)}
        disabled={currentPage === totalPages}
        className="ml-2.5 flex h-10 items-center justify-center rounded-lg border border-gray-300 bg-white px-3.5 py-2.5 text-sm text-gray-700 shadow-theme-xs hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/3"
      >
        Next
      </button>
    </div>
  );
};

export default Pagination;
