import axios, { AxiosInstance, AxiosRequestConfig } from 'axios';
import { map, Observable } from 'rxjs';
import { StorageUtils } from '../utils/StorageUtils';
import ApiResponse from './ApiResponse';
import { fromPromise } from 'rxjs/internal/observable/innerFrom';
import { HttpCode } from './HttpCode';
import { alert } from 'devextreme/ui/dialog';
import toast from '../utils/SharedUtils';
import { dispatch } from '../event/EventHook';
import { Constants } from '../common/Constants';
import AuthResponse from './AuthResponse';

class HttpClient {
  private readonly axiosClient: AxiosInstance;
  private tokenExpiredModalShown: boolean = false;
  private isRefreshing: boolean = false;
  private failedQueue: Array<(token: string) => void> = [];

  private static readonly AUTH_HEADER = 'Authorization';
  private static readonly BEARER_PREFIX = 'Bearer';
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
    });

    this.setupInterceptors();
  }

  private setupInterceptors = () => {
    this.axiosClient.interceptors.request.use((config) => {
      if (config.headers) {
        const token = StorageUtils.getItem('token');
        if (token) {
          config.headers[HttpClient.AUTH_HEADER] = `${HttpClient.BEARER_PREFIX} ${token}`;
        }
      }
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

        if (status === HttpCode.UNAUTHORIZED && originalRequest.url?.endsWith('auth/refresh')) {
          this.failedQueue = [];
          this.processQueue(error, null);
          this.handleTokenRefreshError();
          return Promise.reject(error);
        }

        if (status === HttpCode.UNAUTHORIZED) {
          return this.handleUnauthorizedError(originalRequest, error);
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

  private async handleUnauthorizedError(originalRequest: any, error: any) {
    if (!this.isRefreshing) {
      const refreshToken = StorageUtils.getItem('refreshToken');

      if (!refreshToken) {
        this.handleTokenRefreshError();
      }

      this.isRefreshing = true;

      try {
        const { data } = await this.axiosClient.post<AuthResponse>(
          `${Constants.API_BASE_URL}auth/refresh`,
          {
            refreshToken,
          },
        );
        StorageUtils.setItem('token', data.token);
        StorageUtils.setItem('refreshToken', data.refreshToken);
        this.processQueue(null, data.token);
        return this.axiosClient(originalRequest);
      } catch (refreshError) {
        this.processQueue(refreshError, null);
        this.handleTokenRefreshError();
        return Promise.reject(refreshError);
      } finally {
        this.isRefreshing = false;
      }
    }

    return new Promise((resolve, reject) => {
      this.failedQueue.push((token: string) => {
        originalRequest.headers[HttpClient.AUTH_HEADER] = `${HttpClient.BEARER_PREFIX}${token}`;
        resolve(this.axiosClient(originalRequest));
      });
    });
  }

  private processQueue(error: any, token: string | null) {
    this.failedQueue.forEach((callback) => callback(token!));
    this.failedQueue = [];
  }

  private handleTokenRefreshError() {
    StorageUtils.removeItem('user');
    StorageUtils.removeItem('token');
    StorageUtils.removeItem('refreshToken');
    if (!this.tokenExpiredModalShown) {
      this.tokenExpiredModalShown = true;
      alert(HttpClient.DEFAULT_MESSAGES.unauthorized, 'Error').then(() => {
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
