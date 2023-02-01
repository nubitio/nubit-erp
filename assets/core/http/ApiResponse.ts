import {AxiosResponse, AxiosResponseHeaders} from "axios";

export default class ApiResponse<T> {
    headers: AxiosResponseHeaders;
    data: T;

    constructor(response: AxiosResponse<T>) {
        this.headers = response.headers;
        this.data = response.data;
    }
}