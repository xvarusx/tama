"use client";

import { useEffect } from "react";

export default function DashboardError({
  error,
  retry,
}: {
  error: Error & { digest?: string };
  retry: () => void;
}) {
  useEffect(() => {
    console.error(error);
  }, [error]);

  return (
    <div className="flex min-h-screen items-center justify-center bg-gray-50 p-6 dark:bg-gray-900">
      <div className="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-8 text-center shadow-theme-sm dark:border-gray-800 dark:bg-white/3">
        <h1 className="text-xl font-semibold text-gray-800 dark:text-white/90">
          We couldn&apos;t load your dashboard
        </h1>
        <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
          The authentication service may be unavailable. Please try again.
        </p>
        <button
          type="button"
          onClick={() => retry()}
          className="mt-6 inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-3 text-sm font-medium text-white transition hover:bg-brand-600"
        >
          Try again
        </button>
      </div>
    </div>
  );
}
