import { StorageUtils } from '@shared/lib/StorageUtils';
import { handleSessionExpired, clearAuthSessionStorage } from '@shared/lib/sessionExpiredEvent';
import { requestAuthSessionSync } from '@shared/lib/authSessionSync';
import {
  advanceAuthGeneration,
  AUTH_EPOCH_HEADER,
  AUTH_SESSION_STATE_KEY,
  beginAuthSession,
  endAuthSession,
  getAuthSessionState,
} from '@shared/lib/authSessionState';

type Fetcher = typeof globalThis.fetch;
const SESSION_LOCK = 'nubit-auth-session';
const MUTATING = new Set(['POST', 'PUT', 'PATCH', 'DELETE']);
const CREDENTIAL_PATHS = new Set([
  '/api/auth/login',
  '/api/auth/refresh',
  '/api/auth/logout',
  '/api/auth/impersonate/consume',
]);
let refreshFlight: Promise<void> | null = null;
let temporaryFailure: { generation: string; epoch: string; until: number; error: Error } | null =
  null;

export class AuthRefreshTemporaryError extends Error {
  readonly authRefreshTransient = true;
  notified = false;
  constructor(
    readonly status: number,
    readonly data: Record<string, unknown> = {},
  ) {
    super(
      'No se pudo renovar la sesión temporalmente. Inténtalo de nuevo cuando vuelva la conexión.',
    );
  }
}

function csrfToken(): string | null {
  const cookie = document.cookie.split('; ').find((item) => item.startsWith('CSRF_TOKEN='));
  return cookie ? decodeURIComponent(cookie.slice('CSRF_TOKEN='.length)) : null;
}

function accessToken(): string | null {
  try {
    const token = StorageUtils.getItem('auth_token');
    return typeof token === 'string' && token !== '' ? token : null;
  } catch {
    return null;
  }
}

function snapshot() {
  const state = getAuthSessionState();
  return {
    epoch: state.epoch,
    generation: JSON.stringify([state.generation, csrfToken(), accessToken()]),
  };
}

const changedSession = () =>
  new DOMException('La sesión cambió; se canceló la petición anterior.', 'AbortError');
const expiredResponse = () => Response.json({ message: 'Su sesión ha expirado' }, { status: 401 });

async function sessionLock<T>(operation: () => Promise<T>): Promise<T> {
  return navigator.locks ? navigator.locks.request(SESSION_LOCK, operation) : operation();
}

async function loginSessionLock(operation: () => Promise<Response>): Promise<Response> {
  if (!navigator.locks) return operation();

  // Bound the wait for another tab without stealing its lock or timing out the request itself.
  const controller = new AbortController();
  const timer = window.setTimeout(() => controller.abort(), 5000);
  try {
    return await navigator.locks.request(SESSION_LOCK, { signal: controller.signal }, () => {
      window.clearTimeout(timer);
      return operation();
    });
  } catch (error) {
    if (!controller.signal.aborted) throw error;
    return Response.json(
      {
        message:
          'Otra pestaña está usando la sesión. Cierra las otras pestañas e inténtalo de nuevo.',
      },
      { status: 409 },
    );
  } finally {
    window.clearTimeout(timer);
  }
}

function normalizeToken(data: Record<string, unknown>) {
  if (typeof data.token === 'string' && data.token !== '')
    StorageUtils.setItem('auth_token', data.token);
  else StorageUtils.removeItem('auth_token'); // Cookie mode must not retain a legacy Bearer.
}

async function refresh(sent: ReturnType<typeof snapshot>, original: Fetcher): Promise<void> {
  if (sent.epoch !== getAuthSessionState().epoch) throw changedSession();
  // Cookies may be applied before the response body/token storage is complete.
  // Join the in-tab flight first; check generation only inside the cross-tab
  // lock, after the other tab has finished publishing its renewed credentials.
  if (refreshFlight) return refreshFlight;
  if (getAuthSessionState().active === false) return;
  if (
    temporaryFailure?.epoch === sent.epoch &&
    temporaryFailure.generation === sent.generation &&
    sent.generation === snapshot().generation &&
    temporaryFailure.until > Date.now()
  ) {
    throw temporaryFailure.error;
  }
  refreshFlight = sessionLock(() => renewInLock(sent, original))
    .catch((error: unknown) => {
      if (error instanceof AuthRefreshTemporaryError) {
        temporaryFailure = {
          epoch: sent.epoch,
          generation: sent.generation,
          until: Date.now() + 5000,
          error,
        };
      }
      throw error;
    })
    .finally(() => {
      refreshFlight = null;
    });
  return refreshFlight;
}

async function renewInLock(sent: ReturnType<typeof snapshot>, original: Fetcher): Promise<void> {
  if (sent.epoch !== getAuthSessionState().epoch) throw changedSession();
  if (sent.generation !== snapshot().generation || getAuthSessionState().active === false) return;
  const csrf = csrfToken();
  let response: Response;
  try {
    response = await original('/api/auth/refresh', {
      method: 'POST',
      credentials: 'include',
      cache: 'no-store',
      signal: AbortSignal.timeout(15_000),
      headers: {
        'Accept-Language': navigator.language || 'es',
        ...(csrf ? { 'X-CSRF-Token': csrf } : {}),
      },
    });
  } catch {
    throw new AuthRefreshTemporaryError(503);
  }
  if (sent.epoch !== getAuthSessionState().epoch) throw changedSession();
  if (response.status === 401 || response.status === 403) {
    handleSessionExpired();
    return;
  }
  if (!response.ok)
    throw new AuthRefreshTemporaryError(response.status, await response.json().catch(() => ({})));
  const data = (await response.json().catch(() => null)) as Record<string, unknown> | null;
  if (!data) {
    // Cookies can arrive even when response transfer fails. Do not leave an
    // obsolete Bearer overriding a successfully renewed cookie session.
    if (csrf !== csrfToken()) {
      StorageUtils.removeItem('auth_token');
      advanceAuthGeneration();
      return;
    }
    throw new AuthRefreshTemporaryError(503);
  }
  normalizeToken(data);
  const currentUser = StorageUtils.getItem('user') as Record<string, unknown> | null;
  const user = data.user as Record<string, unknown> | undefined;
  if (currentUser && user?.username === currentUser.username) {
    StorageUtils.setItem('user', { ...currentUser, ...user });
  }
  advanceAuthGeneration();
  temporaryFailure = null;
  requestAuthSessionSync();
}

function dynamicRequest(template: Request, retry: boolean): Request {
  const headers = new Headers(template.headers);
  headers.delete(AUTH_EPOCH_HEADER);
  if (retry) {
    const token = accessToken();
    if (token) headers.set('Authorization', `Bearer ${token}`);
    else headers.delete('Authorization');
  }
  if (MUTATING.has(template.method)) {
    const csrf = csrfToken();
    if (csrf) headers.set('X-CSRF-Token', csrf);
    else headers.delete('X-CSRF-Token');
  }
  return new Request(template.clone(), { headers });
}

async function authenticatedRequest(
  template: Request,
  original: Fetcher,
  send: (request: Request) => Promise<Response>,
): Promise<Response> {
  const sent = snapshot();
  const taggedEpoch = template.headers.get(AUTH_EPOCH_HEADER);
  if (taggedEpoch && taggedEpoch !== sent.epoch) throw changedSession();
  if (getAuthSessionState().active === false) return expiredResponse();
  if (refreshFlight) await refreshFlight;
  if (sent.epoch !== getAuthSessionState().epoch) throw changedSession();
  if (template.signal.aborted) throw template.signal.reason;
  if (getAuthSessionState().active === false) return expiredResponse();
  const request = dynamicRequest(template, sent.generation !== snapshot().generation);
  const response = await send(request);
  if (sent.epoch !== getAuthSessionState().epoch) throw changedSession();
  let retry = response.status === 401;
  if (response.status === 403 && request.headers.get('X-CSRF-Token') !== csrfToken()) {
    const error = await response
      .clone()
      .json()
      .catch(() => ({}));
    retry = error.type === '/errors/csrf-token-invalid';
  }
  if (!retry) return response;
  if (response.status === 401) await refresh(sent, original);
  if (sent.epoch !== getAuthSessionState().epoch) throw changedSession();
  if (getAuthSessionState().active === false) return response;
  if (template.signal.aborted) throw template.signal.reason;
  const retried = await send(dynamicRequest(template, true));
  if (sent.epoch !== getAuthSessionState().epoch) throw changedSession();
  if (retried.status === 401) handleSessionExpired();
  return retried;
}

export function installAuthRecovery(): void {
  const original = globalThis.fetch.bind(globalThis);
  globalThis.fetch = async (input, init) => {
    const url = new URL(
      input instanceof Request ? input.url : String(input),
      window.location.origin,
    );
    if (
      url.origin !== window.location.origin ||
      (url.pathname !== '/api' && !url.pathname.startsWith('/api/')) ||
      url.pathname.startsWith('/api/account/') ||
      url.pathname.startsWith('/api/control-plane/')
    )
      return original(input, init);
    const template = input instanceof Request ? new Request(input, init) : new Request(url, init);
    // Preserve regular SDK JSON/FormData bodies instead of turning every POST
    // into a streaming upload. Request inputs still have a cloneable retry.
    const send = (request: Request) => {
      if (input instanceof Request) return original(request);
      const headers = new Headers(request.headers);
      // `template` was built from the FormData, so it carries a multipart Content-Type with ITS
      // boundary. fetch serialises `init.body` again with a fresh one, and a copied header would no
      // longer match the body: the server then sees no file at all. Let fetch write the header.
      if (init?.body instanceof FormData) headers.delete('Content-Type');
      return original(input, { ...init, headers, signal: request.signal });
    };
    if (template.headers.has('X-Api-Key')) return original(input, init);
    const path = url.pathname;
    if (CREDENTIAL_PATHS.has(path) || path === '/api/auth/select-branch') {
      if (path === '/api/auth/refresh') return send(dynamicRequest(template, false));
      if (path === '/api/auth/logout') endAuthSession('logout');
      const lock = path === '/api/auth/login' ? loginSessionLock : sessionLock;
      return lock(async () => {
        let response = await send(dynamicRequest(template, false));
        if (path === '/api/auth/select-branch' && response.status === 401) {
          await renewInLock(snapshot(), original);
          response = await send(dynamicRequest(template, true));
        }
        if (response.ok && path !== '/api/auth/logout') {
          const data = await response
            .clone()
            .json()
            .catch(() => ({}));
          if (path !== '/api/auth/select-branch') clearAuthSessionStorage();
          normalizeToken(data);
          beginAuthSession();
          temporaryFailure = null;
        }
        return response;
      });
    }
    return authenticatedRequest(template, original, send);
  };
  window.addEventListener('storage', (event) => {
    if (
      ![AUTH_SESSION_STATE_KEY, 'user', 'selectedBranchId', 'selectedBranchName'].includes(
        event.key ?? '',
      )
    )
      return;
    const state = getAuthSessionState();
    if (state.reason === 'expired') handleSessionExpired();
    else requestAuthSessionSync();
  });
}
