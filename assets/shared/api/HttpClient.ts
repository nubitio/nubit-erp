import axios, { AxiosInstance, AxiosRequestConfig } from 'axios';
import { map, Observable } from 'rxjs';
import ApiResponse from './ApiResponse';
import { fromPromise } from 'rxjs/internal/observable/innerFrom';
import { HttpCode } from './HttpCode';
import { alert } from 'devextreme/ui/dialog';
import toast from '../lib/SharedUtils';
import { dispatch } from '../../core/event/EventHook';
import { Constants } from '../../core/common/Constants';
import { StorageUtils } from '../lib/StorageUtils';
import mercureManager from '../../core/mercure/MercureManager';

/**
 * Serializes request params to bracket-notation query strings.
 *
 * Converts arrays-of-objects to the format PHP's DevExtremeFilter expects:
 *   { sort: [{ selector: 'name', desc: false }] }
 *   → "sort[0][selector]=name&sort[0][desc]=false"
 *
 * Plain values and primitive arrays are serialised normally.
 */
function serializeParams(params: Record<string, unknown>): string {
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
      Object.entries(obj as Record<string, unknown>).forEach(([key, val]) => {
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

class HttpClient {
  private readonly axiosClient: AxiosInstance;
  private tokenExpiredModalShown: boolean = false;
  private isRefreshing: boolean = false;
  private refreshPromise: Promise<void> | null = null;
  private failedQueue: Array<{
    resolve: (value?: any) => void;
    reject: (reason?: any) => void;
    request: any;
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

  constructor(private readonly baseUrl: string) {
    this.axiosClient = axios.create({
      baseURL: baseUrl,
      timeout: 30000,
      withCredentials: true,
      paramsSerializer: (params) => serializeParams(params as Record<string, unknown>),
    });

    this.setupInterceptors();
  }

  /**
   * Regex to extract the Mercure hub URL from a `Link` header value.
   *
   * Handles multiple link relations separated by commas, e.g.:
   *   `<https://host/.well-known/mercure>; rel="mercure", </api/products>; rel="self"`
   */
  private static readonly MERCURE_LINK_RE = /<([^>]+)>;\s*rel="mercure"/;

  /**
   * Parse the `Link` response header and, if a `rel="mercure"` entry is found,
   * notify `MercureManager` with the hub URL (idempotent — no-op if already set).
   */
  private discoverMercureHub(linkHeader: string | undefined): void {
    if (!linkHeader || mercureManager.getHubUrl() !== null) {
      return;
    }
    const match = HttpClient.MERCURE_LINK_RE.exec(linkHeader);
    if (match?.[1]) {
      mercureManager.setHubUrl(match[1]);
    }
  }

  private setupInterceptors = () => {
    this.axiosClient.interceptors.request.use((config) => {
      config.headers['Accept-Language'] = this.locale;

      if (config.params && 'skip' in config.params && 'take' in config.params) {
        config.params.page = config.params.skip / config.params.take + 1;
        config.params.itemsPerPage = config.params.take;
        delete config.params.skip;
        delete config.params.take;
      }
      return config;
    });

    // Hub discovery interceptor — reads the `Link` header from every successful response.
    // Must be registered BEFORE the error interceptor so it runs first on success.
    this.axiosClient.interceptors.response.use((response) => {
      try {
        const linkHeader = response.headers['link'] as string | undefined;
        this.discoverMercureHub(linkHeader);
      } catch {
        // Never interrupt the normal response flow
      }
      return response;
    });

    this.axiosClient.interceptors.response.use(
      (response) => response,
      async (error) => {
        const errorResponse = error.response;
        const originalRequest = error.config;
        const status = errorResponse?.status;
        const message = errorResponse?.data?.detail || errorResponse?.data?.message;
        const isAuthRefresh = originalRequest.url?.endsWith('auth/refresh');
        const isAuthLogin = originalRequest.url?.includes('auth/login');

        if (isAuthLogin) {
          return Promise.reject(error);
        }

        if (status === HttpCode.UNAUTHORIZED && isAuthRefresh) {
          this.failedQueue = [];
          this.processQueue(error);
          this.handleTokenRefreshError();
          return Promise.reject(error);
        }

        if (status === HttpCode.UNAUTHORIZED) {
          return this.handleUnauthorizedError(originalRequest);
        }

        if (status === HttpCode.UNPROCESSABLE_ENTITY) {
          const violations = errorResponse?.data?.violations;
          if (violations) {
            dispatch(FORM_ERRORS_EVENT, violations);
          } else {
            this.handleError(status, message);
          }
          return Promise.reject(error);
        }

        this.handleError(status, message);
        return Promise.reject(error);
      },
    );
  };

  private handleUnauthorizedError(originalRequest: any): Promise<any> {
    if (!this.isRefreshing) {
      this.isRefreshing = true;
      this.refreshPromise = this.axiosClient
        .post(`${Constants.API_BASE_URL}auth/refresh`)
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

    return new Promise((resolve, reject) => {
      this.failedQueue.push({ resolve, reject, request: originalRequest });
    });
  }

  private processQueue(error: any) {
    const queue = this.failedQueue;
    this.failedQueue = [];
    queue.forEach(({ resolve, reject, request }) => {
      if (error) {
        reject(error);
      } else {
        this.axiosClient(request).then(resolve).catch(reject);
      }
    });
  }

  private handleTokenRefreshError() {
    if (!this.tokenExpiredModalShown) {
      this.tokenExpiredModalShown = true;
      alert(HttpClient.DEFAULT_MESSAGES.unauthorized, 'Error').then(() => {
        StorageUtils.removeItem('user');
        window.location.href = Constants.BASE_URL;
      });
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
    toast(errorMessage, 'error');

    dispatch(HTTP_ERROR_EVENT, errorMessage);
  }

  private request<T>(
    method: string,
    url: string,
    data?: any,
    config?: AxiosRequestConfig,
  ): Observable<ApiResponse<T>> {
    const abortController = new AbortController();
    const signal = abortController.signal;

    const requestConfig = {
      ...config,
      method,
      url,
      data,
      signal,
    } as AxiosRequestConfig;

    const promise = this.axiosClient.request<T>(requestConfig);

    (promise as any).cancel = () => {
      abortController.abort();
    };

    return fromPromise(promise).pipe(map((response) => new ApiResponse<T>(response)));
  }

  get<T>(url: string, config?: AxiosRequestConfig) {
    return this.request<T>('GET', url, undefined, config);
  }

  post<T>(url: string, data?: any, config?: AxiosRequestConfig) {
    return this.request<T>('POST', url, data, config);
  }

  put<T>(url: string, data?: any, config?: AxiosRequestConfig) {
    return this.request<T>('PUT', url, data, config);
  }

  patch<T>(url: string, data?: any, config?: AxiosRequestConfig) {
    return this.request<T>('PATCH', url, data, {
      ...config,
      headers: {
        ...config?.headers,
        'Content-Type': 'application/merge-patch+json',
      },
    });
  }

  delete<T>(url: string, config?: AxiosRequestConfig) {
    return this.request<T>('DELETE', url, undefined, config);
  }
}

export const FORM_ERRORS_EVENT = 'form-errors';
export const HTTP_ERROR_EVENT = 'http-error';
export const httpClient = new HttpClient('');
