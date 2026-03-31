import { AxiosResponse } from 'axios';

export default class ApiResponse<T> {
  headers: Record<string, string>;
  data: T;

  constructor(response: AxiosResponse<T>) {
    this.headers = Object.fromEntries(
      Object.entries(response.headers).map(([key, value]) => [
        key,
        Array.isArray(value) ? value.join(', ') : String(value ?? ''),
      ]),
    );
    this.data = response.data;
  }
}
