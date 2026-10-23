import { NextResponse } from "next/server";
import { authRequest } from "@/lib/auth/backend";
import { getAuthErrorMessage } from "@/lib/auth/errors";
import { validateAuthRequest } from "@/lib/auth/request";

type RegisterBody = {
  email?: unknown;
  password?: unknown;
};

export async function POST(request: Request) {
  const rejection = validateAuthRequest(request);
  if (rejection) {
    return NextResponse.json(
      { error: rejection.error },
      { status: rejection.status },
    );
  }

  let body: RegisterBody | null = null;

  try {
    body = (await request.json()) as RegisterBody;
  } catch {
    body = null;
  }

  const email = typeof body?.email === "string" ? body.email.trim() : "";
  const password = typeof body?.password === "string" ? body.password : "";

  if (!filterEmail(email) || password.length < 8 || password.length > 4096) {
    return NextResponse.json(
      {
        error:
          "A valid email and a password between 8 and 4096 characters are required.",
      },
      { status: 422 },
    );
  }

  let response: Response;

  try {
    response = await authRequest("/api/user_register", {
      method: "POST",
      body: { email, password },
    });
  } catch {
    return NextResponse.json(
      { error: getAuthErrorMessage(503, null) },
      { status: 503 },
    );
  }

  const data = (await response.json().catch(() => null)) as {
    id?: unknown;
    email?: unknown;
    message?: unknown;
  } | null;

  if (!response.ok) {
    return NextResponse.json(
      { error: getAuthErrorMessage(response.status, data) },
      { status: response.status },
    );
  }

  return NextResponse.json(
    {
      user: {
        id: data?.id,
        email: typeof data?.email === "string" ? data.email : email,
      },
    },
    { status: 201 },
  );
}

function filterEmail(email: string): boolean {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) && email.length <= 180;
}
