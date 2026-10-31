'use client'
import { api } from "@/lib/api";
import { setAuthToken, removeAuthToken } from "@/lib/auth-cookie";

export interface AuthUser {
  id: number;
  email: string;
  roles: string[];
}

interface LoginResponse {
  token: string;
  user: AuthUser;
}

interface RegisterResponse {
  id: number;
  fullName: string;
  email: string;
}

export async function login(
  email: string,
  password: string,
): Promise<AuthUser> {
  const response = await api.post<LoginResponse>("/api/authenticator/user_login", {
    email,
    password,
  });

  const { token, user } = response.data;

  setAuthToken(token);

  return user;
}

export async function me(): Promise<AuthUser> {
  const response = await api.get<AuthUser>("/api/authenticator/me");

  return response.data;
}

export async function logout(): Promise<void> {
  try {
    await api.post("/api/authenticator/user_logout");
  } catch (error) {
    console.error("Failed to revoke the session on the server.", error);
  } finally {
    removeAuthToken();
  }
}
export async function signUp(
  fullName: string,
  email: string,
  password: string,
): Promise<AuthUser> {
  await api.post<RegisterResponse>("/api/authenticator/user_register", {
    fullName,
    email,
    password,
  });

  // /api/user_register returns no token, so sign in with the same credentials.
  return login(email, password);
}
