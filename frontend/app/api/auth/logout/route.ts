import { NextResponse } from "next/server";
import { authRequest } from "@/lib/auth/backend";
import { destroySession, getSessionToken } from "@/lib/auth/session";

export async function POST() {
  const token = await getSessionToken();

  if (token) {
    try {
      await authRequest("/api/user_logout", { method: "POST", token });
    } catch {
      // The local cookie still needs clearing when the service is unavailable.
    }
  }

  await destroySession();

  return NextResponse.json({ ok: true });
}
