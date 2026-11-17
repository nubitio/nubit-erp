/**
 * Maps the URLs the app used while it ran on a hash router (`/#/billing/sales`) to
 * their real-path equivalent (ADR-020, D5).
 *
 * Those URLs are still in the wild — e-mails already sent, bookmarks, installed PWAs
 * whose manifest start_url was `/#/pos`, the user manual — so this is permanent:
 * a few lines, no cost, and it must not be removed on a schedule.
 */

/** Namespaces the server (or another SPA) owns. A hash target inside one needs a real page load. */
const SERVER_OWNED_PREFIXES: readonly string[] = ['register', 'account', 'admin', 'buscar', 'api'];

/** Routes whose single query parameter is a bearer secret: it moves to the fragment (D3). */
const SECRET_ROUTES = new Set(['/reset-password', '/accept-invitation', '/impersonate']);
const SECRET_PARAMS = ['token'] as const;

/** Client routes that were renamed after the hash era (D7). */
const RENAMED_PREFIXES: ReadonlyArray<readonly [from: string, to: string]> = [
  ['/receipt', '/stock-receipt'],
];

export interface LegacyHashTarget {
  /** Path, query and fragment to move to. */
  url: string;
  /** True when the target is served by the server or another SPA, so a replaceState is not enough. */
  reload: boolean;
}

function applyRenames(pathname: string): string {
  for (const [from, to] of RENAMED_PREFIXES) {
    if (pathname === from || pathname.startsWith(`${from}/`)) {
      return `${to}${pathname.slice(from.length)}`;
    }
  }
  return pathname;
}

/**
 * Returns where a legacy hash URL should go, or null when the location is not one.
 *
 * Only `/#/…` at the site root is legacy; any other pathname already is a real route.
 */
export function legacyHashTarget(location: {
  pathname: string;
  hash: string;
}): LegacyHashTarget | null {
  if (location.pathname !== '/') return null;
  if (!location.hash.startsWith('#/')) return null;

  const raw = location.hash.slice(1); // `/billing/sales?highlight=7`
  // A legacy URL is a path with an optional query; `//host` or `/\` forms are not routes.
  if (raw.startsWith('//') || raw.includes('\\')) return null;

  const queryIndex = raw.indexOf('?');
  const rawPath = queryIndex === -1 ? raw : raw.slice(0, queryIndex);
  const query = new URLSearchParams(queryIndex === -1 ? '' : raw.slice(queryIndex + 1));

  const trimmed = rawPath.length > 1 ? rawPath.replace(/\/+$/, '') : rawPath;
  const pathname = applyRenames(trimmed || '/');

  // Judged on the final path: `#/receipt` became `/stock-receipt` (the stock screen), which the SPA owns.
  const reload = SERVER_OWNED_PREFIXES.includes(pathname.split('/')[1] ?? '');

  let fragment = '';
  if (SECRET_ROUTES.has(pathname)) {
    const secret = new URLSearchParams();
    for (const name of SECRET_PARAMS) {
      const value = query.get(name);
      if (value !== null) {
        secret.set(name, value);
        query.delete(name);
      }
    }
    const encoded = secret.toString();
    if (encoded) fragment = `#${encoded}`;
  }

  const search = query.toString();
  return { url: `${pathname}${search ? `?${search}` : ''}${fragment}`, reload };
}

/**
 * Rewrites a legacy hash URL in the address bar before the router mounts.
 * Call once at start-up; it is a no-op for every real-path URL.
 */
export function normalizeLegacyHashUrl(
  win: Pick<Window, 'location' | 'history'> = window,
): boolean {
  const target = legacyHashTarget(win.location);
  if (!target) return false;

  if (target.reload) {
    win.location.replace(target.url);
  } else {
    win.history.replaceState(win.history.state, '', target.url);
  }
  return true;
}
