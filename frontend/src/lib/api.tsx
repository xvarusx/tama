import axios from "axios";
import { getAuthToken } from "./auth-cookie";

export const API_URL =
  import.meta.env.VITE_API_URL ?? "";

export const api = axios.create({
  baseURL: API_URL,
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
      | { error?: string; message?: string }
      | undefined;

    return data?.error ?? data?.message ?? error.message;
  }

  if (error instanceof Error) return error.message;

  return fallback;
}
