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

function stripHashRouterPrefix(value: string): string {
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

  const trimmedValue = stripHashRouterPrefix(value.trim());

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

function getHashRouterLocationParts(hash: string): { pathname: string; search: string } | null {
  if (!hash || hash === '#') {
    return null;
  }

  const normalizedHash = hash.startsWith('#') ? hash.slice(1) : hash;
  const internalPath = normalizedHash.startsWith('/') ? normalizedHash : `/${normalizedHash}`;

  try {
    const parsedHashLocation = new URL(internalPath, APP_ORIGIN_PLACEHOLDER);

    if (parsedHashLocation.origin !== APP_ORIGIN_PLACEHOLDER) {
      return null;
    }

    return {
      pathname: parsedHashLocation.pathname || DEFAULT_POST_LOGIN_ROUTE,
      search: parsedHashLocation.search,
    };
  } catch {
    return null;
  }
}

export function resolveLoginRedirect(search: string, hash = ''): string {
  const redirectFromSearch = getRedirectFromSearch(search);

  if (redirectFromSearch) {
    return redirectFromSearch;
  }

  const hashLocation = getHashRouterLocationParts(hash);

  if (!hashLocation || !isLoginPath(hashLocation.pathname)) {
    return DEFAULT_POST_LOGIN_ROUTE;
  }

  return resolveRedirectFromSearch(hashLocation.search);
}

export function resolveBranchSelectionRedirect(
  preservedRedirect: string | null | undefined,
  search: string,
  hash = '',
): string {
  const preservedInternalRedirect = normalizeInternalRedirectTarget(preservedRedirect);

  if (preservedInternalRedirect) {
    return preservedInternalRedirect;
  }

  return resolveLoginRedirect(search, hash);
}

export function resolveRedirectFromSearch(search: string): string {
  const params = new URLSearchParams(search);
  return resolvePostLoginRedirect(params.get('redirect'));
}

export function getHashRouterPath(hash: string): string {
  if (!hash || hash === '#') {
    return DEFAULT_POST_LOGIN_ROUTE;
  }

  return resolvePostLoginRedirect(hash);
}

export function toHashHref(path: string): string {
  const redirectPath = resolvePostLoginRedirect(path);

  if (redirectPath === DEFAULT_POST_LOGIN_ROUTE) {
    return '/';
  }

  return `/#${redirectPath}`;
}
