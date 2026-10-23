import { redirect } from "next/navigation";
import { getSessionToken } from "@/lib/auth/session";

export const instant = false;

export default async function Home() {
  const token = await getSessionToken();
  redirect(token ? "/dashboard" : "/login");
}
