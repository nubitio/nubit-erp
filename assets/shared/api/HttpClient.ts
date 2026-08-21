import ApiResponse from './ApiResponse';
import { HttpCode } from './HttpCode';
import toast from '../lib/SharedUtils';
import { dispatch, getCoreApiBaseUrl, MercureManager as mercureManager } from '@nubitio/core';
import type { DataRecord } from '@nubitio/core';

const API_BASE_URL = getCoreApiBaseUrl(); // local alias for this legacy client only
import { handleSessionExpired } from '../lib/sessionExpiredEvent';

/**
 * Serializes request params to bracket-notation query strings.
 *
 * Converts arrays-of-objects to the backend data-grid filter format:
 *   { sort: [{ selector: 'name', desc: false }] }
 *   → "sort[0][selector]=name&sort[0][desc]=false"
 *
 * Plain values and primitive arrays are serialised normally.
 */
function serializeParams(params: DataRecord): string {
  const parts: string[] = [];

  function encodeValue(val: unknown): string {
    return encodeURIComponent(String(val));
  }

  function recurse(obj: unknown, prefix: string): void {
    if (obj === null || obj === undefined) return;

    if (Array.isArray(obj)) {
      obj.forEach((item, idx) => {
        recurse(item, `${prefix}[${idx}]`);
      });
    } else if (typeof obj === 'object') {
      Object.entries(obj as DataRecord).forEach(([key, val]) => {
        recurse(val, `${prefix}[${key}]`);
      });
    } else {
      parts.push(`${encodeValue(prefix)}=${encodeValue(obj)}`);
    }
  }

  Object.entries(params).forEach(([key, val]) => {
    if (val === null || val === undefined) return;
    if (Array.isArray(val)) {
      recurse(val, key);
    } else if (typeof val === 'object') {
      recurse(val, key);
    } else {
      parts.push(`${encodeValue(key)}=${encodeValue(val)}`);
    }
  });

  return parts.join('&');
}

export interface RequestConfig {
  params?: DataRecord;
  headers?: Record<string, string>;
  signal?: AbortSignal;
  responseType?: 'json' | 'arraybuffer' | 'blob' | 'text';
}

class HttpClient {
  private tokenExpiredModalShown: boolean = false;
  private isRefreshing: boolean = false;
  private refreshPromise: Promise<void> | null = null;
  private failedQueue: Array<{
    resolve: () => void;
    reject: (reason?: unknown) => void;
    retry: () => Promise<unknown>;
  }> = [];
  private readonly locale: string =
    (typeof navigator !== 'undefined' && navigator.language?.split('-')[0]) || 'es';

  private static readonly DEFAULT_MESSAGES = {
    error: 'Ocurrió un error',
    notFound: 'No se encontró el recurso',
    unauthorized: 'Su sesión ha expirado',
    forbidden: 'Acceso denegado',
    internalServerError: 'Error interno del servidor',
    badRequest: 'Solicitud incorrecta',
    conflict: 'Conflicto de datos',
    tooManyRequests: 'Demasiadas solicitudes',
  };

  constructor(private readonly baseUrl: string) {}

  /**
   * Regex to extract the Mercure hub URL from a `Link` header value.
   */
  private static readonly MERCURE_LINK_RE = /<([^>]+)>;\s*rel="mercure"/;

  /**
   * Parse the `Link` response header and, if a `rel="mercure"` entry is found,
   * notify `MercureManager` with the hub URL (idempotent — no-op if already set).
   */
  private discoverMercureHub(linkHeader: string | null): void {
    if (!linkHeader || mercureManager.getHubUrl() !== null) {
      return;
    }
    const match = HttpClient.MERCURE_LINK_RE.exec(linkHeader);
    if (match?.[1]) {
      mercureManager.setHubUrl(match[1]);
    }
  }

  private buildUrl(url: string, params?: DataRecord): string {
    if (!params || Object.keys(params).length === 0) return url;

    // Convert offset pagination to API Platform page/itemsPerPage parameters.
    const normalized: DataRecord = { ...params };
    if ('skip' in normalized && 'take' in normalized) {
      const skip = Number(normalized.skip);
      const take = Number(normalized.take);
      normalized.page = skip / take + 1;
      normalized.itemsPerPage = take;
      delete normalized.skip;
      delete normalized.take;
    }

    const qs = serializeParams(normalized);
    if (!qs) return url;
    const separator = url.includes('?') ? '&' : '?';
    return `${url}${separator}${qs}`;
  }

  private buildHeaders(extraHeaders?: Record<string, string>): Record<string, string> {
    return {
      'Accept-Language': this.locale,
      ...extraHeaders,
    };
  }

  private async request<T>(
    method: string,
    url: string,
    body?: unknown,
    config?: RequestConfig,
  ): Promise<ApiResponse<T>> {
    const fullUrl = this.buildUrl(url, config?.params);

    const headers = this.buildHeaders(config?.headers);

    // Set Content-Type for JSON bodies (but not for FormData — browser sets it automatically)
    if (body !== undefined && body !== null && !(body instanceof FormData)) {
      headers['Content-Type'] = headers['Content-Type'] ?? 'application/json';
    }

    const init: RequestInit = {
      method,
      headers,
      credentials: 'include',
      signal: config?.signal,
    };

    if (body !== undefined && body !== null) {
      init.body = body instanceof FormData ? body : JSON.stringify(body);
    }

    const isAuthLogin = url.includes('auth/login');
    const isAuthRefresh = url.endsWith('auth/refresh');

    const response = await globalThis.fetch(fullUrl, init);

    // Discover Mercure hub from Link header on any successful response
    try {
      this.discoverMercureHub(response.headers.get('link'));
    } catch {
      // Never interrupt the normal response flow
    }

    if (response.ok) {
      let data: T;
      if (response.status === HttpCode.NO_CONTENT) {
        data = undefined as T;
      } else if (config?.responseType === 'arraybuffer') {
        data = (await response.arrayBuffer()) as unknown as T;
      } else if (config?.responseType === 'blob') {
        data = (await response.blob()) as unknown as T;
      } else if (config?.responseType === 'text') {
        data = (await response.text()) as unknown as T;
      } else {
        data = (await response.json()) as T;
      }
      return new ApiResponse<T>(response, data);
    }

    // --- Error handling ---
    const status = response.status;
    let errorData: { detail?: string; message?: string; violations?: unknown } = {};
    try {
      errorData = await response.json();
    } catch {
      // Body may be empty on some error responses
    }
    const message = errorData?.detail ?? errorData?.message;

    if (isAuthLogin) {
      throw Object.assign(new Error(message ?? HttpClient.DEFAULT_MESSAGES.error), {
        status,
        data: errorData,
      });
    }

    if (status === HttpCode.UNAUTHORIZED && isAuthRefresh) {
      this.failedQueue = [];
      this.processQueue(new Error(HttpClient.DEFAULT_MESSAGES.unauthorized));
      this.handleTokenRefreshError();
      throw Object.assign(new Error(HttpClient.DEFAULT_MESSAGES.unauthorized), {
        status,
        data: errorData,
      });
    }

    if (status === HttpCode.UNAUTHORIZED) {
      return this.handleUnauthorizedError(() => this.request<T>(method, url, body, config));
    }

    if (status === HttpCode.UNPROCESSABLE_ENTITY) {
      if (!errorData?.violations) {
        this.handleError(status, message);
      }
      throw Object.assign(new Error(message ?? HttpClient.DEFAULT_MESSAGES.error), {
        status,
        data: errorData,
      });
    }

    this.handleError(status, message);
    throw Object.assign(new Error(message ?? HttpClient.DEFAULT_MESSAGES.error), {
      status,
      data: errorData,
    });
  }

  private handleUnauthorizedError<T>(retry: () => Promise<T>): Promise<T> {
    if (!this.isRefreshing) {
      this.isRefreshing = true;
      this.refreshPromise = globalThis.fetch(`${API_BASE_URL}auth/refresh`, {
        method: 'POST',
        credentials: 'include',
      })
        .then((res) => {
          if (!res.ok) {
            return res.json().then((data) => {
              const err = Object.assign(new Error('Refresh failed'), { status: res.status, data });
              throw err;
            });
          }
        })
        .then(() => {
          this.processQueue(null);
        })
        .catch((refreshError) => {
          this.processQueue(refreshError);
          this.handleTokenRefreshError();
          throw refreshError;
        })
        .finally(() => {
          this.isRefreshing = false;
          this.refreshPromise = null;
        });
    }

    return new Promise<T>((resolve, reject) => {
      this.failedQueue.push({
        resolve: () =>
          retry()
            .then(resolve as () => void)
            .catch(reject),
        reject,
        retry: retry as () => Promise<unknown>,
      });
    });
  }

  private processQueue(error: unknown) {
    const queue = this.failedQueue;
    this.failedQueue = [];
    queue.forEach(({ resolve, reject }) => {
      if (error) {
        reject(error);
      } else {
        resolve();
      }
    });
  }

  private handleTokenRefreshError() {
    if (!this.tokenExpiredModalShown) {
      this.tokenExpiredModalShown = true;
      handleSessionExpired();
    }
    toast(HttpClient.DEFAULT_MESSAGES.unauthorized, 'error');
  }

  private handleError(status: number | undefined, message: string | undefined) {
    const messageMap: { [key: number]: string } = {
      [HttpCode.BAD_REQUEST]: HttpClient.DEFAULT_MESSAGES.badRequest,
      [HttpCode.FORBIDDEN]: HttpClient.DEFAULT_MESSAGES.forbidden,
      [HttpCode.NOT_FOUND]: HttpClient.DEFAULT_MESSAGES.notFound,
      [HttpCode.CONFLICT]: HttpClient.DEFAULT_MESSAGES.conflict,
      [HttpCode.TOO_MANY_REQUESTS]: HttpClient.DEFAULT_MESSAGES.tooManyRequests,
      [HttpCode.INTERNAL_SERVER_ERROR]: HttpClient.DEFAULT_MESSAGES.internalServerError,
    };

    const errorMessage =
      message || (status ? messageMap[status] : undefined) || HttpClient.DEFAULT_MESSAGES.error;
    toast(errorMessage, 'error', 8000);

    dispatch(HTTP_ERROR_EVENT, errorMessage);
  }

  get<T>(url: string, config?: RequestConfig): Promise<ApiResponse<T>> {
    return this.request<T>('GET', url, undefined, config);
  }

  post<T>(url: string, data?: unknown, config?: RequestConfig): Promise<ApiResponse<T>> {
    return this.request<T>('POST', url, data, config);
  }

  put<T>(url: string, data?: unknown, config?: RequestConfig): Promise<ApiResponse<T>> {
    return this.request<T>('PUT', url, data, config);
  }

  patch<T>(url: string, data?: unknown, config?: RequestConfig): Promise<ApiResponse<T>> {
    return this.request<T>('PATCH', url, data, {
      ...config,
      headers: {
        ...config?.headers,
        'Content-Type': 'application/merge-patch+json',
      },
    });
  }

  delete<T>(url: string, config?: RequestConfig): Promise<ApiResponse<T>> {
    return this.request<T>('DELETE', url, undefined, config);
  }
}

export const HTTP_ERROR_EVENT = 'http-error';
export const httpClient = new HttpClient('');
