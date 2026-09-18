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
 * Cookie-authenticated mutations need X-CSRF-Token. @nubitio/core's HttpClient
 * does not attach it; patch fetch once so hydra/core/legacy clients all comply.
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
