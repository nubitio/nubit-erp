import axios, {Axios, AxiosRequestConfig} from "axios";
import {map, Observable} from "rxjs";
import {fromPromise} from "rxjs/internal/observable/innerFrom";
import {messenger} from "../utils/Messenger";
import {StorageUtils} from "../utils/StorageUtils";
import ApiResponse from "./ApiResponse";

class HttpClient {
    private api: Axios;

    constructor(private readonly baseUrl: string) {
        this.api = axios.create({
            baseURL: baseUrl,
            timeout: 30000,
        });

        this.setupInterceptors();
    }

    private setupInterceptors() {
        this.api.interceptors.request.use((config) => {
            if (config.headers) {
                let token = StorageUtils.getItem('token');
                if (token) {
                    config.headers['Authorization'] = 'Bearer ' + token;
                }
            }
            return config;
        });

        this.api.interceptors.response.use(
            (response) => response,
            (error) => {
                console.log(error)
                if (error.response.status !== 500) {
                    messenger.show('Error', error.response.data.message ?? 'Something went wrong');
                } else {
                    messenger.show('Error', 'Something went wrong');
                }
                return Promise.reject(error);
            });
    }

    get<T>(url: string, config?: AxiosRequestConfig): Observable<ApiResponse<T>> {
        return fromPromise(this.api.get<T>(url, config))
            .pipe(map((response) => new ApiResponse<T>(response)));
    }

    post<T>(url: string, data?: any, config?: AxiosRequestConfig): Observable<ApiResponse<T>> {
        return fromPromise(this.api.post<T>(url, data, config))
            .pipe(map((response) => new ApiResponse<T>(response)));
    }

    put<T>(url: string, data?: any, config?: AxiosRequestConfig): Observable<ApiResponse<T>> {
        return fromPromise(this.api.put<T>(url, data, config))
            .pipe(map((response) => new ApiResponse<T>(response)));
    }

    delete<T>(url: string, config?: AxiosRequestConfig): Observable<ApiResponse<T>> {
        return fromPromise(this.api.delete<T>(url, config))
            .pipe(map((response) => new ApiResponse<T>(response)));
    }
}

export const httpClient = new HttpClient('');