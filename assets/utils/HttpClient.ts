import axios, {Axios, AxiosRequestConfig} from "axios";
import {map, Observable} from "rxjs";
import {fromPromise} from "rxjs/internal/observable/innerFrom";

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
            config.headers['Authorization'] = 'Bearer ' + localStorage.getItem('token');
            return config;
        });

        this.api.interceptors.response.use((response) => response, (error) => {
            if (error.response.status === 401) {
                //window.location.href = '/login';
            }
            return Promise.reject(error);
        });
    }

    get<T>(url: string, config?: AxiosRequestConfig): Observable<T> {
        return fromPromise(this.api.get<T>(url, config))
            .pipe(map((response) => response.data));
    }

    post<T>(url: string, data?: any, config?: AxiosRequestConfig): Observable<T> {
        return fromPromise(this.api.post<T>(url, data, config))
            .pipe(map((response) => response.data));
    }

    put<T>(url: string, data?: any, config?: AxiosRequestConfig): Observable<T> {
        return fromPromise(this.api.put<T>(url, data, config))
            .pipe(map((response) => response.data));
    }

    delete<T>(url: string, config?: AxiosRequestConfig): Observable<T> {
        return fromPromise(this.api.delete<T>(url, config))
            .pipe(map((response) => response.data));
    }
}

export const httpClient = new HttpClient('');