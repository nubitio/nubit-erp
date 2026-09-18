const CSRF_COOKIE_NAME = 'CSRF_TOKEN';
const CSRF_HEADER_NAME = 'X-CSRF-Token';
const MUTATING = new Set(['POST', 'PUT', 'PATCH', 'DELETE']);

function readCookie(name: string): string | undefined {
  if (typeof document === 'undefined') {
    return undefined;
  }

  const prefix = `${encodeURIComponent(name)}=`;
  const cookie = document.cookie.split('; ').find((entry) => entry.startsWith(prefix));

  return cookie ? decodeURIComponent(cookie.slice(prefix.length)) : undefined;
}

function methodOf(init?: RequestInit): string {
  return (init?.method ?? 'GET').toUpperCase();
}

/**
 * Cookie-authenticated mutations need X-CSRF-Token. The `CoreHttpClient`-based
 * clients (`httpClient`, and the provider client used by crud/hydra) attach it
 * themselves from the shared `httpTransport` config; this patch remains the
 * safety net for the few raw `exceptionalFetch` callers that bypass both — the
 * assistant stream and the offline outbox. It is a no-op when a caller already
 * set the header.
 */
export function installCsrfFetch(): void {
  if (typeof globalThis.fetch !== 'function') {
    return;
  }

  const original = globalThis.fetch.bind(globalThis);
  globalThis.fetch = (input: RequestInfo | URL, init?: RequestInit): Promise<Response> => {
    const method = input instanceof Request ? input.method.toUpperCase() : methodOf(init);
    if (!MUTATING.has(method)) {
      return original(input, init);
    }

    const token = readCookie(CSRF_COOKIE_NAME);
    if (token === undefined || token === '') {
      return original(input, init);
    }

    const headers = new Headers(input instanceof Request ? input.headers : init?.headers);
    if (!headers.has(CSRF_HEADER_NAME)) {
      headers.set(CSRF_HEADER_NAME, token);
    }

    if (input instanceof Request) {
      return original(new Request(input, { headers }));
    }

    return original(input, { ...init, headers });
  };
}
