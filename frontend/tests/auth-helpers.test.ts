import { describe, expect, it } from "vitest";
import { getAuthErrorMessage } from "@/lib/auth/errors";
import { isProtectedPath } from "@/lib/auth/paths";
import { getSessionCookieOptions } from "@/lib/auth/cookie-options";

describe("getAuthErrorMessage", () => {
  it("uses generic copy for 401", () => {
    expect(getAuthErrorMessage(401, { message: "Invalid credentials." })).toBe(
      "Invalid email or password.",
    );
  });
  it("uses generic copy for 429", () => {
    expect(getAuthErrorMessage(429, null)).toBe(
      "Too many attempts. Please try again later.",
    );
  });
  it("passes through the backend message for 409 and 422", () => {
    expect(getAuthErrorMessage(409, { message: "Email exists." })).toBe(
      "Email exists.",
    );
    expect(getAuthErrorMessage(422, { message: "Password too short." })).toBe(
      "Password too short.",
    );
  });
  it("uses service copy for 5xx", () => {
    expect(getAuthErrorMessage(503, { message: "boom" })).toBe(
      "The authentication service is unavailable. Please try again.",
    );
  });
  it("falls back for unknown status and unreadable body", () => {
    expect(getAuthErrorMessage(418, "nope", "Custom fallback.")).toBe(
      "Custom fallback.",
    );
  });
});

describe("isProtectedPath", () => {
  it("matches dashboard and nested dashboard paths only", () => {
    expect(isProtectedPath("/dashboard")).toBe(true);
    expect(isProtectedPath("/dashboard/clients/acme")).toBe(true);
    expect(isProtectedPath("/dashboardx")).toBe(false);
    expect(isProtectedPath("/login")).toBe(false);
  });
});

describe("getSessionCookieOptions", () => {
  it("sets a 7-day max age when remembered", () => {
    expect(getSessionCookieOptions(true)).toEqual({
      httpOnly: true,
      secure: false,
      sameSite: "lax",
      path: "/",
      maxAge: 604800,
    });
  });
  it("omits maxAge for a session cookie", () => {
    const options = getSessionCookieOptions(false);
    expect(options).not.toHaveProperty("maxAge");
    expect(options.httpOnly).toBe(true);
  });
});
