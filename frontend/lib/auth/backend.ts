import "server-only";

import { AUTH_SERVICE_URL } from "./config";

export type AuthRequestOptions = {
  method?: string;
  body?: unknown;
  token?: string;
};

export async function authRequest(
  path: string,
  options: AuthRequestOptions = {},
): Promise<Response> {
  const headers = new Headers({ Accept: "application/json" });

  if (options.body !== undefined) {
    headers.set("Content-Type", "application/json");
  }
  if (options.token) {
    headers.set("X-AUTH-TOKEN", options.token);
  }

  return fetch(new URL(path, AUTH_SERVICE_URL), {
    method: options.method ?? "GET",
    headers,
    body: options.body === undefined ? undefined : JSON.stringify(options.body),
    cache: "no-store",
    signal: AbortSignal.timeout(5000),
  });
}
