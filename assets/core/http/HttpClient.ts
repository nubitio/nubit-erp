import axios, {Axios, AxiosRequestConfig} from "axios";
import {map, Observable} from "rxjs";
import {StorageUtils} from "../utils/StorageUtils";
import ApiResponse from "./ApiResponse";
import {fromPromise} from "rxjs/internal/observable/innerFrom";
import {HttpCode} from "./HttpCode";
import {confirm} from "devextreme/ui/dialog";
import toast from "../utils/SharedUtils";
import {dispatch} from "../event/EventHook";

class HttpClient {
    private axiosClient: Axios;
    private tokenExpiredModalShown: boolean = false;

    constructor(private readonly baseUrl: string) {
        this.axiosClient = axios.create({
            baseURL: baseUrl,
            timeout: 30000,
        });

        this.setupInterceptors();
    }

    private setupInterceptors = () => {
        const AUTH_HEADER = 'Authorization';
        const BEARER_PREFIX = 'Bearer ';
        const DEFAULT_ERROR_MESSAGE = 'Ocurrió un error';
        const DEFAULT_NOT_FOUND_MESSAGE = 'No se encontró el recurso';
        const DEFAULT_UNAUTHORIZED_MESSAGE = 'Su sesión ha expirado';
        const DEFAULT_FORBIDDEN_MESSAGE = 'Acceso denegado';
        const DEFAULT_INTERNAL_SERVER_ERROR_MESSAGE = 'Error interno del servidor';
        const DEFAULT_BAD_REQUEST_MESSAGE = 'Solicitud incorrecta';

        this.axiosClient.interceptors.request.use((config) => {
            if (config.headers) {
                let token = StorageUtils.getItem('token');
                if (token) {
                    config.headers[AUTH_HEADER] = `${BEARER_PREFIX}${token}`;
                }
            }
            if (config.params && config.params.skip && config.params.take) {
                config.params.page = config.params.skip / config.params.take + 1;
                config.params.itemsPerPage = config.params.take;
            }
            return config;
        });

        this.axiosClient.interceptors.response.use(
            (response) => response,
            (error) => {
                switch (error.response.status) {
                    case HttpCode.UNAUTHORIZED:
                        const message = error.response?.data?.message ?? DEFAULT_UNAUTHORIZED_MESSAGE;
                        if (!this.tokenExpiredModalShown) {
                            this.tokenExpiredModalShown = true;
                            let token = StorageUtils.getItem('token');
                            if (token) {
                                confirm(message, 'Error').then(() => {
                                    StorageUtils.removeItem('token');
                                    StorageUtils.removeItem('user');
                                    window.location.href = '/';
                                });
                            } else {
                                toast(message, 'error');
                            }
                        }
                        break;
                    case HttpCode.BAD_REQUEST:
                        const badRequestMessage = error.response?.data?.message ?? DEFAULT_BAD_REQUEST_MESSAGE;
                        toast(badRequestMessage, 'error')
                        break;
                    case HttpCode.UNPROCESSABLE_ENTITY:
                        const violations = error.response?.data?.violations;
                        if (violations) {
                            dispatch(FORM_ERRORS_EVENT, violations);
                        }
                        break;
                    case HttpCode.FORBIDDEN:
                        const forbiddenMessage = error.response?.data?.message ?? DEFAULT_FORBIDDEN_MESSAGE;
                        toast(forbiddenMessage, 'error')
                        break;
                    case HttpCode.NOT_FOUND:
                        const notFoundMessage = error.response?.data?.message ?? DEFAULT_NOT_FOUND_MESSAGE;
                        toast(notFoundMessage, 'warning')
                        break;
                    case HttpCode.INTERNAL_SERVER_ERROR:
                        const internalServerErrorMessage = error.response?.data?.message ?? DEFAULT_INTERNAL_SERVER_ERROR_MESSAGE;
                        toast(internalServerErrorMessage, 'error')
                        break;
                    default:
                        const errorMessage = error.response?.data?.message ?? DEFAULT_ERROR_MESSAGE;
                        toast(errorMessage, 'error')
                        break;
                }
                return Promise.reject(error);
            });
    }

    private request<T>(
        method: string,
        url: string,
        data?: any,
        config?: AxiosRequestConfig
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
        }

        return fromPromise(promise).pipe(
            map((response) => new ApiResponse<T>(response))
        );
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

    delete<T>(url: string, config?: AxiosRequestConfig) {
        return this.request<T>('DELETE', url, undefined, config);
    }
}

export const FORM_ERRORS_EVENT = 'form-errors';
export const httpClient = new HttpClient('');
