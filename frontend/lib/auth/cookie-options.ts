import { SESSION_MAX_AGE_SECONDS } from "./config";

export type SessionCookieOptions = {
  httpOnly: true;
  secure: boolean;
  sameSite: "lax";
  path: "/";
  maxAge?: number;
};

export function getSessionCookieOptions(
  remember: boolean,
): SessionCookieOptions {
  const options: SessionCookieOptions = {
    httpOnly: true,
    secure: process.env.NODE_ENV === "production",
    sameSite: "lax",
    path: "/",
  };
  if (remember) options.maxAge = SESSION_MAX_AGE_SECONDS;
  return options;
}
