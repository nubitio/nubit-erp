import { exceptionalFetch } from '@shared/api/network';

export interface ApiRequestConfig {
  method: string;
  url: string;
  headers?: Record<string, string>;
  body?: unknown;
  /** Sent as is when given (it is what a signature covers); otherwise `body` is serialized. */
  rawBody?: string;
}

export interface ApiResult {
  ok: boolean;
  status?: number;
  data?: unknown;
  errorMessage?: string;
  errorCode?: string;
  action?: string;
  numericCode?: number;
  recoverable: boolean;
  requiresLogin?: boolean;
}

type ApiErrorPayload = {
  detail?: unknown;
  message?: unknown;
  title?: unknown;
  'hydra:description'?: unknown;
  violations?: unknown;
  errorCode?: unknown;
  action?: unknown;
  numericCode?: unknown;
};

const asApiErrorPayload = (payload: unknown): ApiErrorPayload =>
  payload && typeof payload === 'object' ? (payload as ApiErrorPayload) : {};

const parseJsonSafely = async (response: Response): Promise<unknown> => {
  try {
    return await response.json();
  } catch {
    return null;
  }
};

const parseErrorMessage = (payload: unknown, fallback: string) => {
  const errorPayload = asApiErrorPayload(payload);
  const violations = Array.isArray(errorPayload.violations)
    ? errorPayload.violations
        .map((item: unknown) =>
          item && typeof item === 'object' ? (item as { message?: unknown }).message : undefined,
        )
        .filter(
          (message: unknown): message is string =>
            typeof message === 'string' && message.length > 0,
        )
    : [];

  return (
    (typeof errorPayload.detail === 'string' ? errorPayload.detail : undefined) ||
    (typeof errorPayload.message === 'string' ? errorPayload.message : undefined) ||
    (typeof errorPayload['hydra:description'] === 'string'
      ? errorPayload['hydra:description']
      : undefined) ||
    violations.join('\n') ||
    (typeof errorPayload.title === 'string' ? errorPayload.title : undefined) ||
    fallback
  );
};

const parseErrorMetadata = (payload: unknown) => {
  const errorPayload = asApiErrorPayload(payload);
  return {
    errorCode: typeof errorPayload.errorCode === 'string' ? errorPayload.errorCode : undefined,
    action: typeof errorPayload.action === 'string' ? errorPayload.action : undefined,
    numericCode:
      typeof errorPayload.numericCode === 'number' ? errorPayload.numericCode : undefined,
  };
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
        body:
          config.rawBody ?? (config.body === undefined ? undefined : JSON.stringify(config.body)),
      },
      'offline-runtime',
    );

  try {
    const response = await makeRequest();

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
    const metadata = parseErrorMetadata(payload);

    // An obsolete client must update, not permanently fail queued sales.
    if (status >= 500 || status === 429 || status === 426) {
      return { ok: false, status, errorMessage, ...metadata, recoverable: true };
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

    return { ok: false, status, errorMessage, ...metadata, recoverable: false };
  } catch (error) {
    return {
      ok: false,
      status: error instanceof Error && 'status' in error ? Number(error.status) : undefined,
      recoverable: true,
      errorMessage: error instanceof Error ? error.message : 'Error de red',
    };
  }
};
