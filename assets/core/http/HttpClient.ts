import axios, {Axios, AxiosRequestConfig} from "axios";
import {map, Observable} from "rxjs";
import {messenger} from "../utils/Messenger";
import {StorageUtils} from "../utils/StorageUtils";
import ApiResponse from "./ApiResponse";
import {fromPromise} from "rxjs/internal/observable/innerFrom";

class HttpClient {
    private axiosClient: Axios;

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
        const DEFAULT_ERROR_MESSAGE = 'Something went wrong';

        this.axiosClient.interceptors.request.use((config) => {
            if (config.headers) {
                let token = StorageUtils.getItem('token');
                if (token) {
                    config.headers[AUTH_HEADER] = `${BEARER_PREFIX}${token}`;
                }
            }
            return config;
        });

        this.axiosClient.interceptors.response.use(
            (response) => response,
            (error) => {
                if (error.response.status !== 500) {
                    messenger.show('Error', error.response.data.message ?? DEFAULT_ERROR_MESSAGE);
                } else {
                    messenger.show('Error', DEFAULT_ERROR_MESSAGE);
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

        return fromPromise(promise)
            .pipe(map((response) => new ApiResponse<T>(response)));
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

export const httpClient = new HttpClient('');