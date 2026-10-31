const COOKIE_NAME = "auth_token";

export function setAuthToken(token: string) {
  const secure = window.location.protocol === "https:" ? "; Secure" : "";

  document.cookie =
    `${COOKIE_NAME}=${encodeURIComponent(token)}; Path=/; SameSite=Lax${secure}`;
}

export function getAuthToken(): string | null {
  const prefix = `${COOKIE_NAME}=`;

  const cookie = document.cookie
    .split("; ")
    .find((item) => item.startsWith(prefix));

  if (!cookie) return null;

  return decodeURIComponent(cookie.slice(prefix.length));
}

export function removeAuthToken() {
  document.cookie =
    `${COOKIE_NAME}=; Path=/; Max-Age=0; SameSite=Lax`;
}
