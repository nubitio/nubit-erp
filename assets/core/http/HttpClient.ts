import axios, {AxiosInstance, AxiosRequestConfig} from 'axios';
import {map, Observable} from 'rxjs';
import ApiResponse from './ApiResponse';
import {fromPromise} from 'rxjs/internal/observable/innerFrom';
import {HttpCode} from './HttpCode';
import {alert} from 'devextreme/ui/dialog';
import toast from '../utils/SharedUtils';
import {dispatch} from '../event/EventHook';
import {Constants} from '../common/Constants';
import {StorageUtils} from "../utils/StorageUtils";

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
  };

  constructor(private readonly baseUrl: string) {
    this.axiosClient = axios.create({
      baseURL: baseUrl,
      timeout: 30000,
      withCredentials: true,
    });

    this.setupInterceptors();
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

    this.axiosClient.interceptors.response.use(
      (response) => response,
      async (error) => {
        const errorResponse = error.response;
        const originalRequest = error.config;
        const status = errorResponse?.status;
        const message = errorResponse?.data?.detail || errorResponse?.data?.message;
        const isAuthRefresh = originalRequest.url?.endsWith('auth/refresh');
        const isAuthLogin = originalRequest.url?.includes('auth/login');

        if (status === HttpCode.UNAUTHORIZED && isAuthRefresh) {
          this.failedQueue = [];
          this.processQueue(error);
          this.handleTokenRefreshError();
          return Promise.reject(error);
        }

        if (status === HttpCode.UNAUTHORIZED && isAuthLogin) {
          toast(message || HttpClient.DEFAULT_MESSAGES.unauthorized, 'error');
          return Promise.reject(error);
        }

        if (status === HttpCode.UNAUTHORIZED) {
          return this.handleUnauthorizedError(originalRequest);
        }

        if (status === HttpCode.UNPROCESSABLE_ENTITY) {
          const violations = errorResponse?.data?.violations;
          if (violations) {
            dispatch(FORM_ERRORS_EVENT, violations);
          }
          return Promise.reject(error);
        }

        this.handleError(status, message);
        return Promise.reject(error);
      },
    );
  };

  private async handleUnauthorizedError(originalRequest: any) {
    if (!this.isRefreshing) {
      this.isRefreshing = true;
      this.refreshPromise = this.axiosClient
        .post(`${Constants.API_BASE_URL}auth/refresh`)
        .then(() => {
          this.processQueue();
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
      this.failedQueue.push({resolve, reject, request: originalRequest});
      this.refreshPromise?.catch((error) => reject(error));
    });
  }

  private processQueue(error?: any) {
    this.failedQueue.forEach(({resolve, reject, request}) => {
      if (error) {
        reject(error);
      } else {
        resolve(this.axiosClient(request));
      }
    });
    this.failedQueue = [];
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

  private handleError(status: number, message: string | undefined) {
    const messageMap: { [key: number]: string } = {
      [HttpCode.BAD_REQUEST]: HttpClient.DEFAULT_MESSAGES.badRequest,
      [HttpCode.FORBIDDEN]: HttpClient.DEFAULT_MESSAGES.forbidden,
      [HttpCode.NOT_FOUND]: HttpClient.DEFAULT_MESSAGES.notFound,
      [HttpCode.CONFLICT]: HttpClient.DEFAULT_MESSAGES.conflict,
      [HttpCode.INTERNAL_SERVER_ERROR]: HttpClient.DEFAULT_MESSAGES.internalServerError,
    };

    const errorMessage = message || messageMap[status] || HttpClient.DEFAULT_MESSAGES.error;
    if (status === HttpCode.BAD_REQUEST || status === HttpCode.INTERNAL_SERVER_ERROR) {
      toast(errorMessage, 'error');
    }

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
