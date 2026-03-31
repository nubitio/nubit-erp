import { AxiosResponse } from 'axios';

export default class ApiResponse<T> {
  headers: Record<string, string>;
  data: T;

  constructor(response: AxiosResponse<T>) {
    this.headers = response.headers;
    this.data = response.data;
  }
}
