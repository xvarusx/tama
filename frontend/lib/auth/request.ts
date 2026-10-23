export type AuthRequestRejection = {
  status: 403 | 415;
  error: string;
};

type RequestLike = {
  headers: Headers;
  url: string;
};

/**
 * Login and registration are state-changing browser endpoints. HTML forms can
 * send a cross-site POST without CORS, so require JSON (which forms cannot
 * send) and reject browser requests that identify themselves as cross-site.
 * Non-browser clients without Origin/Sec-Fetch-Site remain supported.
 */
export function validateAuthRequest(
  request: RequestLike,
): AuthRequestRejection | null {
  const contentType = request.headers
    .get("content-type")
    ?.split(";")[0]
    .trim()
    .toLowerCase();

  if (contentType !== "application/json") {
    return {
      status: 415,
      error: "Content-Type must be application/json.",
    };
  }

  if (isCrossSiteRequest(request)) {
    return {
      status: 403,
      error: "Cross-site requests are not allowed.",
    };
  }

  return null;
}

function isCrossSiteRequest(request: RequestLike): boolean {
  const fetchSite = request.headers.get("sec-fetch-site");
  if (fetchSite && fetchSite !== "same-origin" && fetchSite !== "none") {
    return true;
  }

  const origin = request.headers.get("origin");
  if (!origin) return false;

  const host =
    firstHeaderValue(request.headers.get("x-forwarded-host")) ??
    request.headers.get("host");
  if (!host) return true;

  const protocol =
    firstHeaderValue(request.headers.get("x-forwarded-proto")) ??
    new URL(request.url).protocol.replace(":", "");

  try {
    return new URL(origin).origin !== new URL(`${protocol}://${host}`).origin;
  } catch {
    return true;
  }
}

function firstHeaderValue(value: string | null): string | null {
  return value?.split(",")[0]?.trim() || null;
}
