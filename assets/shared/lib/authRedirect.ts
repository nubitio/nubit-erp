const LOGIN_PATH = '/login';
export const DEFAULT_POST_LOGIN_ROUTE = '/';

const APP_ORIGIN_PLACEHOLDER = 'https://efact.local';

function isLoginPath(pathname: string): boolean {
  if (!pathname) {
    return false;
  }

  const normalizedPath = pathname !== '/' ? pathname.replace(/\/+$/, '') || '/' : pathname;
  return normalizedPath === LOGIN_PATH;
}

/**
 * Redirect targets captured while the app ran on a hash router look like `/#/billing/sales`;
 * they may still sit in a stored `?redirect=` value, so they are read as the path they meant.
 */
function stripLegacyHashPrefix(value: string): string {
  if (value === '#' || value === '/#') {
    return DEFAULT_POST_LOGIN_ROUTE;
  }

  if (value.startsWith('/#/')) {
    return value.slice(2);
  }

  if (value.startsWith('#/')) {
    return value.slice(1);
  }

  return value;
}

export function normalizeInternalRedirectTarget(value: string | null | undefined): string | null {
  if (typeof value !== 'string') {
    return null;
  }

  const trimmedValue = stripLegacyHashPrefix(value.trim());

  if (!trimmedValue) {
    return null;
  }

  if (
    trimmedValue.startsWith('//') ||
    /^[a-zA-Z][a-zA-Z\d+.-]*:/.test(trimmedValue) ||
    trimmedValue.includes('\\') ||
    /(^|\/)\.\.?($|[/?])/.test(trimmedValue)
  ) {
    return null;
  }

  if (!trimmedValue.startsWith('/')) {
    return null;
  }

  try {
    const parsedTarget = new URL(trimmedValue, APP_ORIGIN_PLACEHOLDER);

    if (parsedTarget.origin !== APP_ORIGIN_PLACEHOLDER || parsedTarget.hash) {
      return null;
    }

    if (isLoginPath(parsedTarget.pathname)) {
      return null;
    }

    return `${parsedTarget.pathname || DEFAULT_POST_LOGIN_ROUTE}${parsedTarget.search}`;
  } catch {
    return null;
  }
}

export function resolvePostLoginRedirect(value: string | null | undefined): string {
  return normalizeInternalRedirectTarget(value) ?? DEFAULT_POST_LOGIN_ROUTE;
}

export function buildLoginRedirectSearch(pathname: string, search = ''): string {
  const redirect = normalizeInternalRedirectTarget(`${pathname}${search}`);

  if (!redirect) {
    return '';
  }

  return `?redirect=${encodeURIComponent(redirect)}`;
}

export function getRedirectFromSearch(search: string): string | null {
  const params = new URLSearchParams(search);
  return normalizeInternalRedirectTarget(params.get('redirect'));
}

/** Where to go after login: the `?redirect=` of the current URL, else the default route. */
export function resolveLoginRedirect(search: string): string {
  return getRedirectFromSearch(search) ?? DEFAULT_POST_LOGIN_ROUTE;
}

export function resolveBranchSelectionRedirect(
  preservedRedirect: string | null | undefined,
  search: string,
): string {
  return normalizeInternalRedirectTarget(preservedRedirect) ?? resolveLoginRedirect(search);
}
