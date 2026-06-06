import type { ElementType } from 'react';
import type { AppRouteDefinition } from './types';

/**
 * Returns a list-route entry plus a deep-link entry for a CRUD page.
 *
 * Use this in a feature's `routes.tsx` instead of a plain `{ path, element }`
 * object whenever the resource can be opened in `viewMode: 'page'`. The
 * second entry enables URL deep-linking to a specific record (`/foo/123`)
 * or to the create form (`/foo/new`).
 *
 * @example
 * ```ts
 * export const catalogRoutes: AppRouteDefinition[] = [
 *   ...crudRoute('/category', CategoryPage),
 *   ...crudRoute('/supplier', SupplierPage),
 * ];
 * ```
 */
export function crudRoute(path: string, element: ElementType): AppRouteDefinition[] {
  return [
    { path, element },
    { path: `${path}/:id?`, element },
  ];
}
