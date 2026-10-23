import "server-only";

import { cache } from "react";
import { redirect } from "next/navigation";
import { authRequest } from "./backend";
import { getSessionToken } from "./session";

export type AuthUser = {
  id: number;
  email: string;
  roles: string[];
};

export const getCurrentUser = cache(async (): Promise<AuthUser> => {
  const token = await getSessionToken();
  if (!token) redirect("/login");

  const response = await authRequest("/api/me", { token });

  if (response.status === 401) redirect("/api/auth/clear");
  if (!response.ok) {
    throw new Error(`Authentication service returned ${response.status}`);
  }

  const user = (await response.json()) as Partial<AuthUser>;
  if (typeof user.id !== "number" || typeof user.email !== "string") {
    throw new Error("Authentication service returned an invalid user.");
  }

  return {
    id: user.id,
    email: user.email,
    roles: Array.isArray(user.roles) ? user.roles : ["ROLE_USER"],
  };
});
