import type ApiResponse from './ApiResponse';
import { httpClient } from './HttpClient';

/**
 * Canonical entry point for frontend business/API calls.
 * New business integrations should build on this client or wrappers over it.
 */
export const businessHttpClient = httpClient;

export const httpRequest = <T>(request: Promise<ApiResponse<T>>): Promise<ApiResponse<T>> => {
  return request;
};

export const httpRequestData = <T>(request: Promise<ApiResponse<T>>): Promise<T> => {
  return request.then((response) => response.data);
};

export type DirectFetchReason =
  | 'browser-native-api'
  | 'service-worker-runtime'
  | 'sdk-required'
  | 'offline-runtime'
  | 'api-documentation';

/**
 * Escape hatch for the few places where native fetch remains valid.
 * Do not use this for business API calls; use `businessHttpClient` instead.
 */
export const exceptionalFetch = (
  input: RequestInfo | URL,
  init: RequestInit | undefined,
  reason: DirectFetchReason,
): Promise<Response> => {
  void reason;
  return fetch(input, init);
};
