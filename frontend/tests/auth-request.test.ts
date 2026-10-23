import { describe, expect, it } from "vitest";
import { validateAuthRequest } from "@/lib/auth/request";

function makeRequest(headers: Record<string, string> = {}) {
  return {
    headers: new Headers(headers),
    url: "http://localhost:3000/api/auth/login",
  };
}

describe("validateAuthRequest", () => {
  it("accepts JSON requests", () => {
    expect(
      validateAuthRequest(
        makeRequest({ "content-type": "application/json; charset=utf-8" }),
      ),
    ).toBeNull();
  });

  it("rejects a text/plain body", () => {
    expect(
      validateAuthRequest(makeRequest({ "content-type": "text/plain" })),
    ).toEqual({
      status: 415,
      error: "Content-Type must be application/json.",
    });
  });

  it("rejects a missing content type", () => {
    expect(validateAuthRequest(makeRequest())).toEqual({
      status: 415,
      error: "Content-Type must be application/json.",
    });
  });

  it("rejects a cross-site JSON request", () => {
    expect(
      validateAuthRequest(
        makeRequest({
          "content-type": "application/json",
          origin: "https://evil.example",
          host: "localhost:3000",
        }),
      ),
    ).toEqual({
      status: 403,
      error: "Cross-site requests are not allowed.",
    });
  });

  it("accepts a same-origin JSON request", () => {
    expect(
      validateAuthRequest(
        makeRequest({
          "content-type": "application/json",
          origin: "http://localhost:3000",
          host: "localhost:3000",
        }),
      ),
    ).toBeNull();
  });

  it("uses forwarded host and protocol when behind a proxy", () => {
    expect(
      validateAuthRequest(
        makeRequest({
          "content-type": "application/json",
          origin: "https://erp.example.com",
          host: "internal:3000",
          "x-forwarded-host": "erp.example.com",
          "x-forwarded-proto": "https",
        }),
      ),
    ).toBeNull();
  });

  it("rejects a cross-site fetch even without an Origin header", () => {
    expect(
      validateAuthRequest(
        makeRequest({
          "content-type": "application/json",
          "sec-fetch-site": "cross-site",
        }),
      ),
    ).toEqual({
      status: 403,
      error: "Cross-site requests are not allowed.",
    });
  });
});
