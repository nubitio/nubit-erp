export default class ApiResponse<T> {
  headers: Record<string, string>;
  data: T;

  constructor(response: Response, data: T) {
    this.headers = Object.fromEntries(
      Array.from(response.headers.entries()).map(([key, value]) => [key, value]),
    );
    this.data = data;
  }
}
