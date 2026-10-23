import { beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("@/lib/auth/backend", () => ({ authRequest: vi.fn() }));
vi.mock("@/lib/auth/session", () => ({
  createSession: vi.fn(),
  destroySession: vi.fn(),
}));

import { authRequest } from "@/lib/auth/backend";
import { createSession, destroySession } from "@/lib/auth/session";
import { POST as login } from "@/app/api/auth/login/route";
import { POST as register } from "@/app/api/auth/register/route";
import { GET as clear } from "@/app/api/auth/clear/route";

const mockedAuthRequest = vi.mocked(authRequest);
const mockedCreateSession = vi.mocked(createSession);
const mockedDestroySession = vi.mocked(destroySession);

function postRequest(
  path: string,
  body: string,
  headers: Record<string, string> = { "content-type": "application/json" },
) {
  return new Request(`http://localhost:3000${path}`, { method: "POST", headers, body });
}

beforeEach(() => {
  vi.clearAllMocks();
});

describe("login route request validation", () => {
  it("rejects a non-JSON body without calling the auth service", async () => {
    const response = await login(
      postRequest(
        "/api/auth/login",
        JSON.stringify({ email: "user@example.com", password: "secret" }),
        { "content-type": "text/plain" },
      ),
    );

    expect(response.status).toBe(415);
    await expect(response.json()).resolves.toEqual({
      error: "Content-Type must be application/json.",
    });
    expect(mockedAuthRequest).not.toHaveBeenCalled();
  });

  it("rejects a cross-site request without calling the auth service", async () => {
    const response = await login(
      postRequest(
        "/api/auth/login",
        JSON.stringify({ email: "user@example.com", password: "secret" }),
        {
          "content-type": "application/json",
          origin: "https://evil.example",
          host: "localhost:3000",
        },
      ),
    );

    expect(response.status).toBe(403);
    expect(mockedAuthRequest).not.toHaveBeenCalled();
  });

  it("sets the session and omits the backend token from the response", async () => {
    mockedAuthRequest.mockResolvedValue(
      Response.json({
        token: "backend-token",
        user: { id: 7, email: "user@example.com", roles: ["ROLE_USER"] },
      }),
    );

    const response = await login(
      postRequest(
        "/api/auth/login",
        JSON.stringify({
          email: "user@example.com",
          password: "secret",
          remember: true,
        }),
      ),
    );
    const body = await response.json();

    expect(response.status).toBe(200);
    expect(body).toEqual({
      user: { id: 7, email: "user@example.com", roles: ["ROLE_USER"] },
    });
    expect(JSON.stringify(body)).not.toContain("backend-token");
    expect(mockedCreateSession).toHaveBeenCalledWith("backend-token", true);
  });
});

describe("register route request validation", () => {
  it("rejects a non-JSON body", async () => {
    const response = await register(
      postRequest(
        "/api/auth/register",
        JSON.stringify({ email: "user@example.com", password: "secret123" }),
        { "content-type": "text/plain" },
      ),
    );

    expect(response.status).toBe(415);
    expect(mockedAuthRequest).not.toHaveBeenCalled();
  });
});

describe("clear route", () => {
  it("uses a relative login redirect", async () => {
    const response = await clear();

    expect(mockedDestroySession).toHaveBeenCalled();
    expect(response.status).toBe(307);
    expect(response.headers.get("location")).toBe("/login");
  });
});
