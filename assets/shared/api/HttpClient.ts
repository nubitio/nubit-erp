import ApiResponse from './ApiResponse';
import { HttpCode } from './HttpCode';
import toast from '../lib/SharedUtils';
import {
  createCoreHttpClient,
  dispatch,
  getCoreApiBaseUrl,
  type CoreHttpError,
  type CoreHttpResponse,
  type CoreRequestConfig,
} from '@nubitio/core';
import { handleSessionExpired } from '../lib/sessionExpiredEvent';
import { StorageUtils } from '../lib/StorageUtils';
import { httpTransport } from './httpTransport';

export const HTTP_ERROR_EVENT = 'http-error';

/**
 * Per-request options. Alias of the library's `CoreRequestConfig` — kept as a
 * named export so existing call-sites keep importing it from here.
 */
export type RequestConfig = CoreRequestConfig;

const DEFAULT_MESSAGES = {
  error: 'Ocurrió un error',
  notFound: 'No se encontró el recurso',
  unauthorized: 'Su sesión ha expirado',
  forbidden: 'Acceso denegado',
  internalServerError: 'Error interno del servidor',
  badRequest: 'Solicitud incorrecta',
  conflict: 'Conflicto de datos',
  tooManyRequests: 'Demasiadas solicitudes',
};

const MESSAGE_BY_STATUS: Record<number, string> = {
  [HttpCode.BAD_REQUEST]: DEFAULT_MESSAGES.badRequest,
  [HttpCode.FORBIDDEN]: DEFAULT_MESSAGES.forbidden,
  [HttpCode.NOT_FOUND]: DEFAULT_MESSAGES.notFound,
  [HttpCode.CONFLICT]: DEFAULT_MESSAGES.conflict,
  [HttpCode.TOO_MANY_REQUESTS]: DEFAULT_MESSAGES.tooManyRequests,
  [HttpCode.INTERNAL_SERVER_ERROR]: DEFAULT_MESSAGES.internalServerError,
};

/**
 * The one `CoreHttpClient` behind every direct API call in the app.
 *
 * All transport concerns — Bearer token, CSRF, correlation id, the
 * refresh-on-401 loop, and Mercure hub discovery — live in `@nubitio/core`
 * and are configured once in `httpTransport`. This module only layers the
 * app's error UX on top, which is why it passes no `onError`/`onUnauthorized`
 * to the client: the adapter needs the request URL to tell a failed login
 * apart from an expired session, and the client's callbacks do not receive it.
 */
const coreHttpClient = createCoreHttpClient({
  ...httpTransport,
  baseUrl: getCoreApiBaseUrl(),
});

/**
 * Session-expired is shown at most once per page load: several concurrent
 * requests can each fail their refresh, and one modal is enough.
 */
let tokenExpiredModalShown = false;

/**
 * Applies this app's error UX to a failed request and returns the error to
 * throw. Login is deliberately exempt: the login form renders the message
 * inline, and a rejected login is not an expired session.
 */
function isAbortError(error: unknown): boolean {
  return (
    typeof error === 'object' &&
    error !== null &&
    (error as { name?: unknown }).name === 'AbortError'
  );
}

function toAppError(error: unknown, url: string): unknown {
  // A request cancelled on purpose (superseded poll, unmounted view) is not a
  // failure the user needs to hear about; callers already ignore it.
  if (isAbortError(error)) {
    return error;
  }

  const coreError = error as CoreHttpError;
  const status = coreError?.status;

  if (url.includes('auth/login')) {
    return error;
  }

  if (status === HttpCode.UNAUTHORIZED) {
    StorageUtils.removeItem('auth_token');
    if (!tokenExpiredModalShown) {
      tokenExpiredModalShown = true;
      handleSessionExpired();
    }
    toast(DEFAULT_MESSAGES.unauthorized, 'error');
    return error;
  }

  // Field-level validation errors are rendered by the form that submitted the
  // request; a toast on top of that would just duplicate the message.
  if (status === HttpCode.UNPROCESSABLE_ENTITY && coreError.data?.violations) {
    return error;
  }

  const detail = coreError.data?.detail ?? coreError.data?.message;
  const message =
    detail || (status ? MESSAGE_BY_STATUS[status] : undefined) || DEFAULT_MESSAGES.error;

  toast(message, 'error', 8000);
  dispatch(HTTP_ERROR_EVENT, message);

  return error;
}

/**
 * Thin adapter that keeps the app's `ApiResponse<T>` contract (a plain-object
 * `headers` record and `.data`) while delegating the request itself to
 * `CoreHttpClient`. Call-sites are unchanged.
 */
class HttpClient {
  private async run<T>(
    request: Promise<CoreHttpResponse<T>>,
    url: string,
  ): Promise<ApiResponse<T>> {
    try {
      const response = await request;
      return new ApiResponse<T>(response.response, response.data);
    } catch (error) {
      throw toAppError(error, url);
    }
  }

  get<T>(url: string, config?: RequestConfig): Promise<ApiResponse<T>> {
    return this.run(coreHttpClient.get<T>(url, config), url);
  }

  post<T>(url: string, data?: unknown, config?: RequestConfig): Promise<ApiResponse<T>> {
    return this.run(coreHttpClient.post<T>(url, data, config), url);
  }

  put<T>(url: string, data?: unknown, config?: RequestConfig): Promise<ApiResponse<T>> {
    return this.run(coreHttpClient.put<T>(url, data, config), url);
  }

  patch<T>(url: string, data?: unknown, config?: RequestConfig): Promise<ApiResponse<T>> {
    return this.run(coreHttpClient.patch<T>(url, data, config), url);
  }

  delete<T>(url: string, config?: RequestConfig): Promise<ApiResponse<T>> {
    return this.run(coreHttpClient.delete<T>(url, config), url);
  }
}

export const httpClient = new HttpClient();
