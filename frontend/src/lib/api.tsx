import axios from "axios";
import { getAuthToken } from "./auth-cookie";

/**
 * All API calls go through the same origin. In development Vite proxies
 * `/api` to the nginx gateway (see `vite.config.ts`), so no per-service
 * host or CORS configuration is needed.
 */
export const api = axios.create({
  headers: {
    "Content-Type": "application/json",
  },
});

api.interceptors.request.use((config) => {
  const token = getAuthToken();

  if (token) {
    config.headers.set("X-AUTH-TOKEN", token);
  }

  return config;
});

export function getApiErrorMessage(
  error: unknown,
  fallback = "Something went wrong. Please try again.",
): string {
  if (axios.isAxiosError(error)) {
    const data = error.response?.data as
      | {
          error?: string | { message?: string }
          message?: string
          detail?: string
        }
      | undefined;

    if (typeof data?.error === "string") return data.error;
    if (data?.error && typeof data.error === "object") {
      return data.error.message ?? error.message;
    }

    return data?.detail ?? data?.message ?? error.message;
  }

  if (error instanceof Error) return error.message;

  return fallback;
}
