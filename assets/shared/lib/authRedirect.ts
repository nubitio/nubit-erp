const LOGIN_PATH = '/login';

export function buildLoginRedirectSearch(pathname: string, search = ''): string {
  if (!pathname || pathname === LOGIN_PATH) {
    return '';
  }

  const redirect = `${pathname}${search}`;
  return `?redirect=${encodeURIComponent(redirect)}`;
}

export function getRedirectFromSearch(search: string): string | null {
  const params = new URLSearchParams(search);
  const redirect = params.get('redirect');

  if (!redirect || !redirect.startsWith('/') || redirect === LOGIN_PATH) {
    return null;
  }

  return redirect;
}

export function getHashRouterPath(hash: string): string {
  if (!hash || hash === '#') {
    return '/';
  }

  const normalizedHash = hash.startsWith('#') ? hash.slice(1) : hash;
  return normalizedHash.startsWith('/') ? normalizedHash : `/${normalizedHash}`;
}

export function toHashHref(path: string): string {
  if (!path || path === '/') {
    return '/';
  }

  return `/#${path.startsWith('/') ? path : `/${path}`}`;
}
