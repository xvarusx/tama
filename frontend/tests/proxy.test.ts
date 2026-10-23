import { describe, expect, it } from "vitest";
import { NextRequest } from "next/server";
import { config, proxy } from "@/proxy";

describe("proxy", () => {
  it("redirects protected paths without a session cookie", () => {
    const response = proxy(new NextRequest("http://localhost:3000/dashboard/clients"));

    expect(response.status).toBe(307);
    expect(response.headers.get("location")).toBe("http://localhost:3000/login");
  });

  it("allows protected paths with a session cookie", () => {
    const request = new NextRequest("http://localhost:3000/dashboard", {
      headers: { cookie: "auth_session=token" },
    });

    expect(proxy(request).status).toBe(200);
  });

  it("does not guard unrelated paths", () => {
    const response = proxy(new NextRequest("http://localhost:3000/login"));

    expect(response.status).toBe(200);
  });

  it("only matches dashboard paths", () => {
    expect(config.matcher).toEqual(["/dashboard/:path*"]);
  });
});
