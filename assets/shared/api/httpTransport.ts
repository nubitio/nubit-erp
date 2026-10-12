import type { CoreHttpClientConfig } from '@nubitio/core';
import { StorageUtils } from '@shared/lib/StorageUtils';
import { AUTH_EPOCH_HEADER, getAuthSessionState } from '@shared/lib/authSessionState';

const CSRF_COOKIE_NAME = 'CSRF_TOKEN';
const CSRF_HEADER_NAME = 'X-CSRF-Token';

function readCookie(name: string): string | undefined {
  if (typeof document === 'undefined') {
    return undefined;
  }

  const prefix = `${encodeURIComponent(name)}=`;
  const cookie = document.cookie.split('; ').find((entry) => entry.startsWith(prefix));

  return cookie ? decodeURIComponent(cookie.slice(prefix.length)) : undefined;
}

/**
 * The transport half of this app's HTTP configuration: everything about *how*
 * a request is sent, independent of how a caller reacts to a failure.
 *
 * Both HTTP clients in the app build on this one object —
 *  - the React provider client (`CoreProvider` in `AppProviders`), which serves
 *    all crud/hydra traffic, and
 *  - the module-level `httpClient` used by direct call-sites,
 *
 * so the Bearer token, CSRF header, correlation id and refresh/login paths are
 * defined exactly once. Error UX (toasts, session-expired modal, the
 * `HTTP_ERROR_EVENT` bus) stays per client, because the two clients
 * deliberately differ there: crud surfaces failures through its own grid/form
 * UI, while direct call-sites rely on the shared toast.
 *
 * `baseUrl` is intentionally omitted — `CoreHttpProvider` falls back to the
 * value configured in `CoreConfigProvider`, and the module-level client passes
 * it explicitly (see `HttpClient.ts`).
 */
export const httpTransport: CoreHttpClientConfig = {
  // One shared fetch recovery layer coordinates all SDK instances and outbox.
  autoRefresh: false,
  refreshPath: 'auth/refresh',
  loginPath: 'auth/login',

  /**
   * Read on every request (not captured at construction) so a rotated token is
   * picked up without rebuilding the client.
   */
  getAuthHeaders: (): Record<string, string> => {
    const token = StorageUtils.getItem('auth_token');
    return {
      [AUTH_EPOCH_HEADER]: getAuthSessionState().epoch,
      ...(typeof token === 'string' && token !== '' ? { Authorization: `Bearer ${token}` } : {}),
    };
  },

  /**
   * Cookie double-submit. `CoreHttpClient` only consults this for mutating
   * methods (POST/PUT/PATCH/DELETE), so read methods never look it up.
   */
  getCsrfToken: () => readCookie(CSRF_COOKIE_NAME),
  csrfHeaderName: CSRF_HEADER_NAME,

  /**
   * The backend reads `X-Request-Id` and stamps it onto its spans and log
   * records (`App\Efact\EventListener\TenantListener`), so a failed call can be
   * traced from the UI to the server without a separate correlation scheme.
   */
  enableCorrelationId: true,
};
