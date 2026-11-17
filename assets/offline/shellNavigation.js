/**
 * Service-worker routing for the tenant SPA's HTML shell (ADR-020, D4).
 *
 * The shell is one document served by Symfony for every client route. The
 * service worker therefore keeps ONE cached copy, under the key `/`, and answers
 * any app navigation with it when the network is down. Without this, an offline
 * reload of `/billing/sales` would only work if that exact URL had been visited
 * online before — fatal for the POS.
 *
 * These functions are serialised into `public/sw.js` by Workbox (`toString()`),
 * so each one MUST be self-contained: no imports, no references to module scope,
 * no TypeScript-only syntax. Keep the reserved list inside the function body.
 * The same list lives in HomeController::RESERVED_PREFIXES; SpaRoutingTest keeps
 * the server side honest, shellNavigation.test.ts this side.
 */

/**
 * `urlPattern` for the app-shell runtime cache: a top-level navigation to a URL
 * the tenant SPA owns. Anything Symfony or another SPA serves (`/admin`,
 * `/account`, `/register`, the public voucher pages, the API…) must never be
 * cached under the shell key, or the shell would be replaced by another page.
 *
 * @param {{ request: { mode: string, method?: string }, url: { pathname: string } }} context
 * @returns {boolean}
 */
export function isTenantShellNavigation({ request, url }) {
  if (request.mode !== 'navigate') return false;
  if (request.method && request.method !== 'GET') return false;

  const reserved = [
    'api',
    'admin',
    'account',
    'register',
    'buscar',
    'receipt',
    'build',
    'uploads',
    'health',
    'logo',
    'webhook',
    'css',
    'js',
    'images',
    'themes',
    'assets',
    'bundles',
    '.well-known',
    'sw.js',
  ];
  const first = url.pathname.split('/')[1] || '';
  if (reserved.includes(first)) return false;
  if (first.startsWith('workbox-') || first.startsWith('_')) return false;

  // A file (extension on the last segment) is not an SPA route.
  const last = url.pathname.split('/').filter(Boolean).pop() || '';
  return !/\.[A-Za-z0-9]+$/.test(last);
}

/**
 * Workbox plugin hook: read and write every shell navigation under `/`,
 * whatever the browser asked for. The network request still goes to the real URL.
 *
 * @param {{ request: { url: string } }} context
 * @returns {string}
 */
export function shellCacheKeyWillBeUsed({ request }) {
  const url = new URL(request.url);
  return new URL('/', url.origin).href;
}
