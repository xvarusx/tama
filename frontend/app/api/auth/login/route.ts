import { NextResponse } from "next/server";
import { authRequest } from "@/lib/auth/backend";
import { getAuthErrorMessage } from "@/lib/auth/errors";
import { validateAuthRequest } from "@/lib/auth/request";
import { createSession } from "@/lib/auth/session";

type LoginBody = {
  email?: unknown;
  password?: unknown;
  remember?: unknown;
};

export async function POST(request: Request) {
  const rejection = validateAuthRequest(request);
  if (rejection) {
    return NextResponse.json(
      { error: rejection.error },
      { status: rejection.status },
    );
  }

  let body: LoginBody | null = null;

  try {
    body = (await request.json()) as LoginBody;
  } catch {
    body = null;
  }

  if (
    !body ||
    typeof body.email !== "string" ||
    typeof body.password !== "string" ||
    body.email.trim() === "" ||
    body.password === ""
  ) {
    return NextResponse.json(
      { error: "Email and password are required." },
      { status: 400 },
    );
  }

  let response: Response;

  try {
    response = await authRequest("/api/user_login", {
      method: "POST",
      body: { email: body.email.trim(), password: body.password },
    });
  } catch {
    return NextResponse.json(
      { error: getAuthErrorMessage(503, null) },
      { status: 503 },
    );
  }

  const data = (await response.json().catch(() => null)) as {
    token?: unknown;
    user?: { id?: unknown; email?: unknown; roles?: unknown };
  } | null;

  if (!response.ok) {
    return NextResponse.json(
      {
        error: getAuthErrorMessage(
          response.status,
          data,
          "Invalid email or password.",
        ),
      },
      { status: response.status },
    );
  }

  if (
    !data ||
    typeof data.token !== "string" ||
    !data.user ||
    typeof data.user.email !== "string"
  ) {
    return NextResponse.json(
      { error: "Unexpected response from the authentication service." },
      { status: 502 },
    );
  }

  await createSession(data.token, body.remember === true);

  return NextResponse.json({
    user: {
      id: data.user.id,
      email: data.user.email,
      roles: Array.isArray(data.user.roles) ? data.user.roles : ["ROLE_USER"],
    },
  });
}
