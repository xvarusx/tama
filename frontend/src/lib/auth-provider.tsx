import { useCallback, useEffect, useState, type ReactNode } from "react";
import axios from "axios";
import { getAuthToken, removeAuthToken } from "@/lib/auth-cookie";
import { AuthContext, type AuthStatus } from "@/lib/auth-context";
import { logout, me, type AuthUser } from "@/services/auth-api";

type SessionResult =
  | { status: "authenticated"; user: AuthUser }
  | { status: "anonymous" }
  | { status: "error" };

async function resolveSession(): Promise<SessionResult> {
  if (!getAuthToken()) {
    return { status: "anonymous" };
  }

  try {
    return { status: "authenticated", user: await me() };
  } catch (error) {
    if (axios.isAxiosError(error) && error.response?.status === 401) {
      removeAuthToken();
      return { status: "anonymous" };
    }

    // Backend unreachable or failing: keep the cookie and let the user retry.
    return { status: "error" };
  }
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<AuthUser | null>(null);
  const [status, setStatus] = useState<AuthStatus>(() =>
    getAuthToken() ? "checking" : "anonymous",
  );

  const applySession = useCallback((result: SessionResult) => {
    if (result.status === "authenticated") {
      setUser(result.user);
      setStatus("authenticated");
      return;
    }

    setUser(null);
    setStatus(result.status);
  }, []);

  useEffect(() => {
    if (!getAuthToken()) return;

    let cancelled = false;

    resolveSession().then((result) => {
      if (!cancelled) {
        applySession(result);
      }
    });

    return () => {
      cancelled = true;
    };
  }, [applySession]);

  const refresh = useCallback(async () => {
    setStatus("checking");
    applySession(await resolveSession());
  }, [applySession]);

  const signIn = useCallback((nextUser: AuthUser) => {
    setUser(nextUser);
    setStatus("authenticated");
  }, []);

  const signOut = useCallback(async () => {
    await logout();

    setUser(null);
    setStatus("anonymous");
  }, []);

  return (
    <AuthContext.Provider value={{ status, user, signIn, signOut, refresh }}>
      {children}
    </AuthContext.Provider>
  );
}
