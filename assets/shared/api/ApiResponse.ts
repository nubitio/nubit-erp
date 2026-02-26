import { AxiosResponse } from 'axios';

export default class ApiResponse<T> {
  headers: any;
  data: T;

  constructor(response: AxiosResponse<T>) {
    this.headers = response.headers;
    this.data = response.data;
  }
}
