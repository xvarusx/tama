export const AUTH_SERVICE_URL =
  process.env.AUTH_SERVICE_URL ?? "http://127.0.0.1:8001";
export const SESSION_COOKIE = "auth_session";
export const SESSION_MAX_AGE_SECONDS = 60 * 60 * 24 * 7;
