import { Button } from "@/components/ui/button";
import { useAuth } from "@/lib/auth-context";

export function AuthStatusScreen() {
  const { status, refresh } = useAuth();

  if (status === "error") {
    return (
      <div className="flex min-h-svh flex-col items-center justify-center gap-4 p-6 text-center">
        <div className="flex flex-col gap-1">
          <h1 className="text-lg font-semibold">Can't reach the server</h1>
          <p className="text-sm text-muted-foreground">
            Your session is still saved. Check your connection and try again.
          </p>
        </div>
        <Button onClick={() => void refresh()}>Retry</Button>
      </div>
    );
  }

  return (
    <div className="flex min-h-svh items-center justify-center p-6">
      <p className="text-sm text-muted-foreground">Checking your session…</p>
    </div>
  );
}
