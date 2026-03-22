import { exceptionalFetch } from '@shared/api/network';

export interface ApiRequestConfig {
  method: string;
  url: string;
  headers?: Record<string, string>;
  body?: unknown;
}

export interface ApiResult {
  ok: boolean;
  status?: number;
  data?: any;
  errorMessage?: string;
  recoverable: boolean;
  requiresLogin?: boolean;
}

const parseJsonSafely = async (response: Response) => {
  try {
    return await response.json();
  } catch {
    return null;
  }
};

const parseErrorMessage = (payload: any, fallback: string) => {
  return (
    payload?.detail ||
    payload?.message ||
    payload?.['hydra:description'] ||
    payload?.title ||
    fallback
  );
};

const refreshAuthToken = async (): Promise<boolean> => {
  try {
    const response = await exceptionalFetch(
      '/api/auth/refresh',
      {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Accept-Language': (typeof navigator !== 'undefined' && navigator.language) || 'es',
        },
      },
      'offline-runtime',
    );
    return response.ok;
  } catch {
    return false;
  }
};

export const sendApiRequest = async (config: ApiRequestConfig): Promise<ApiResult> => {
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    ...config.headers,
  };

  const makeRequest = () =>
    exceptionalFetch(
      config.url,
      {
        method: config.method,
        headers,
        credentials: 'include',
        body: config.body === undefined ? undefined : JSON.stringify(config.body),
      },
      'offline-runtime',
    );

  try {
    let response = await makeRequest();

    if (response.status === 401) {
      const refreshed = await refreshAuthToken();
      if (!refreshed) {
        return {
          ok: false,
          status: 401,
          errorMessage: 'Requiere iniciar sesión',
          recoverable: false,
          requiresLogin: true,
        };
      }
      response = await makeRequest();
    }

    if (response.ok) {
      return {
        ok: true,
        status: response.status,
        data: await parseJsonSafely(response),
        recoverable: false,
      };
    }

    const payload = await parseJsonSafely(response);
    const status = response.status;
    const errorMessage = parseErrorMessage(payload, `Error HTTP ${status}`);

    if (status >= 500 || status === 429) {
      return { ok: false, status, errorMessage, recoverable: true };
    }

    if (status === 401) {
      return {
        ok: false,
        status,
        errorMessage: 'Requiere iniciar sesión',
        recoverable: false,
        requiresLogin: true,
      };
    }

    return { ok: false, status, errorMessage, recoverable: false };
  } catch (error) {
    return {
      ok: false,
      recoverable: true,
      errorMessage: error instanceof Error ? error.message : 'Error de red',
    };
  }
};
