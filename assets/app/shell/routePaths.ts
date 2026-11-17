/**
 * Paths of the routes declared outside `app-routes.tsx`.
 *
 * Kept in one module so `spaPaths.ts` can enumerate every client route without
 * parsing JSX. The server-side collision test (tests/Efact/Web/SpaRoutingTest.php,
 * ADR-020) consumes that list: a client path that also exists as a Symfony route
 * would be answered by Symfony, not by the SPA, once routing is path-based.
 */
export const AUTH_ROUTE_PATHS = {
  login: '/login',
  impersonate: '/impersonate',
  forgotPassword: '/forgot-password',
  resetPassword: '/reset-password',
  acceptInvitation: '/accept-invitation',
} as const;

export const FULLSCREEN_ROUTE_PATHS = {
  pos: '/pos',
} as const;
