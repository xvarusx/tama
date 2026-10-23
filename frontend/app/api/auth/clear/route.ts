import { NextResponse } from "next/server";
import { destroySession } from "@/lib/auth/session";

export async function GET() {
  await destroySession();
  return new NextResponse(null, {
    status: 307,
    headers: { Location: "/login" },
  });
}
