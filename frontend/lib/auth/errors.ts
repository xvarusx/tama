function readableMessage(body: unknown): string | null {
  if (typeof body !== "object" || body === null) return null;
  const message = (body as { message?: unknown }).message;
  return typeof message === "string" && message.trim() !== ""
    ? message
    : null;
}

export function getAuthErrorMessage(
  status: number,
  body: unknown,
  fallback = "Something went wrong. Please try again.",
): string {
  if (status === 401 || status === 403) {
    return "Invalid email or password.";
  }
  if (status === 429) {
    return "Too many attempts. Please try again later.";
  }
  if (status >= 500) {
    return "The authentication service is unavailable. Please try again.";
  }
  if (status === 409 || status === 422) {
    return readableMessage(body) ?? fallback;
  }
  return readableMessage(body) ?? fallback;
}
