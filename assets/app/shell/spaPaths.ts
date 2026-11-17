import type { AppRouteDefinition } from '@app/router/types';
import { appRoutes } from './app-routes';
import { AUTH_ROUTE_PATHS, FULLSCREEN_ROUTE_PATHS } from './routePaths';

function joinPath(parent: string, child: string): string {
  if (child.startsWith('/')) return child;
  return `${parent.replace(/\/+$/, '')}/${child}`;
}

function flatten(routes: AppRouteDefinition[], parent = ''): string[] {
  return routes.flatMap(({ path, children }) => {
    const full = joinPath(parent, path);
    return [full, ...(children ? flatten(children, full) : [])];
  });
}

/**
 * Every path the tenant SPA answers, as written in its route tables
 * (`:param` segments and `?` optional markers are kept). Sorted and de-duplicated.
 *
 * Catch-all (`*`) entries are not paths and are dropped.
 */
export function collectSpaPaths(): string[] {
  const all = [
    ...flatten(appRoutes),
    ...Object.values(AUTH_ROUTE_PATHS),
    ...Object.values(FULLSCREEN_ROUTE_PATHS),
  ].filter((path) => !path.includes('*'));

  return [
    ...new Set(all.map((path) => (path.length > 1 ? path.replace(/\/+$/, '') : path))),
  ].sort();
}
