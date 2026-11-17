import { createContext, useContext, type ReactNode } from 'react';
import { createBrowserRouter, RouterProvider, type RouteObject } from 'react-router-dom';

/**
 * Path-based data router for the tenant SPA (ADR-020, D1).
 *
 * A data router is what unlocks `useBlocker` (the dirty-form guard in ADR-019) and
 * `useNavigation`. It has ONE splat route that renders the whole app, so every
 * existing `<Routes>` tree keeps working as descendant routes and no route table
 * is rewritten.
 *
 * The router is created once and outlives renders, so the app is handed to the
 * splat route through context instead of being captured in the route definition
 * (a captured element would go stale as props change).
 */
const SlotContext = createContext<ReactNode>(null);

const Slot = () => <>{useContext(SlotContext)}</>;

export const APP_ROUTE_OBJECTS: RouteObject[] = [{ path: '*', element: <Slot /> }];

type DataRouter = ReturnType<typeof createBrowserRouter>;

let appRouter: DataRouter | undefined;

/** Created lazily and once: building it registers a popstate listener on `window`. */
function getAppRouter(): DataRouter {
  appRouter ??= createBrowserRouter(APP_ROUTE_OBJECTS);
  return appRouter;
}

export interface RouterRootProps {
  children: ReactNode;
  /** Override for tests (e.g. `createMemoryRouter(APP_ROUTE_OBJECTS, …)`). */
  router?: DataRouter;
}

export const RouterRoot = ({ children, router }: RouterRootProps) => (
  <SlotContext.Provider value={children}>
    <RouterProvider router={router ?? getAppRouter()} />
  </SlotContext.Provider>
);
