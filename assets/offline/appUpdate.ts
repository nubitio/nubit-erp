import { useSyncExternalStore } from 'react';
import { exceptionalFetch } from '@shared/api/network';
import policy from '../../config/app-update.json';

interface ReleaseInfo {
  revision: string | null;
  minimumOfflineContractVersion: number;
}

interface UpdateState {
  available: boolean;
  required: boolean;
  busy: number;
  updating: boolean;
  error: string | null;
}

let state: UpdateState = {
  available: false,
  required: false,
  busy: 0,
  updating: false,
  error: null,
};
const listeners = new Set<() => void>();
let registration: ServiceWorkerRegistration | null = null;
let reloadPending = false;
let reloadStarted = false;
const OPERATION_LOCK = 'nubit-app-critical-operation';

const publish = (changes: Partial<UpdateState>) => {
  state = { ...state, ...changes };
  listeners.forEach((listener) => listener());
};

export const getAppUpdateState = () => state;
export function useAppUpdate() {
  return useSyncExternalStore((listener) => {
    listeners.add(listener);
    return () => listeners.delete(listener);
  }, getAppUpdateState);
}

export function requiresClientUpdate(release: ReleaseInfo): boolean {
  return release.minimumOfflineContractVersion > policy.offlineContractVersion;
}

export function installUpdateAwareFetch(): void {
  const original = globalThis.fetch.bind(globalThis);
  globalThis.fetch = (input, init) => {
    const url = new URL(
      input instanceof Request ? input.url : String(input),
      window.location.origin,
    );
    const method = (
      init?.method ?? (input instanceof Request ? input.method : 'GET')
    ).toUpperCase();
    if (
      url.origin !== window.location.origin ||
      !url.pathname.startsWith('/api/') ||
      url.pathname.startsWith('/api/auth/') ||
      !['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)
    )
      return original(input, init);

    const headers = new Headers(
      init?.headers ?? (input instanceof Request ? input.headers : undefined),
    );
    headers.set('X-App-Contract-Version', String(policy.offlineContractVersion));
    return runCriticalOperation(async () => {
      const response =
        input instanceof Request
          ? await original(new Request(input, { ...init, headers }))
          : await original(input, { ...init, headers });
      if (response.status === 426) publish({ required: true, available: true });
      // Keep the update lock through response transfer. Streaming assistant
      // responses must remain consumable incrementally by their caller.
      if (!response.headers.get('content-type')?.includes('text/event-stream')) {
        await response.clone().arrayBuffer();
      }
      return response;
    });
  };
}

function reloadWhenSafe() {
  if (!reloadPending || state.busy > 0 || reloadStarted) return;
  reloadStarted = true;
  window.location.reload();
}

async function warmAppShell(): Promise<boolean> {
  // First installation claims an already-loaded page. Client-side navigation
  // does not fetch HTML, so populate the shell without interrupting login/POS.
  // The shell is cached under `/` regardless of the current route: the service
  // worker answers every app navigation with that one copy (ADR-020, D4;
  // see assets/offline/shellNavigation.js).
  try {
    const url = new URL('/', window.location.origin);
    const response = await exceptionalFetch(
      url,
      { cache: 'no-store', credentials: 'same-origin' },
      'service-worker-runtime',
    );
    if (response.ok && response.headers.get('content-type')?.includes('text/html')) {
      await (await caches.open('app-shell')).put(url, response);
      return true;
    }
    return false;
  } catch {
    // A later successful controlled navigation can populate the shell instead.
    return false;
  }
}

/** Checkout and outbox sync share a lock with worker activation across tabs. */
export async function runCriticalOperation<T>(operation: () => Promise<T>): Promise<T> {
  const run = async () => {
    if (state.required || state.updating)
      throw new Error('Actualiza la aplicación para continuar.');
    publish({ busy: state.busy + 1 });
    try {
      return await operation();
    } finally {
      publish({ busy: state.busy - 1 });
      reloadWhenSafe();
    }
  };
  return navigator.locks ? navigator.locks.request(OPERATION_LOCK, { mode: 'shared' }, run) : run();
}

export async function checkAppVersion(): Promise<void> {
  if (!navigator.onLine) return;
  try {
    const response = await exceptionalFetch(
      '/health/app-version',
      { cache: 'no-store', signal: AbortSignal.timeout(5000) },
      'service-worker-runtime',
    );
    if (response.ok) {
      const release: ReleaseInfo = await response.json();
      if (!Number.isInteger(release.minimumOfflineContractVersion)) return;
      const loaded = [...document.querySelectorAll<HTMLScriptElement>('script[src]')]
        .map((script) => new URL(script.src).pathname.split('/').pop())
        .find((name) => name?.startsWith('app-') && name.endsWith('.js'));
      const required = requiresClientUpdate(release);
      publish({
        required,
        available:
          required ||
          !!registration?.waiting ||
          !!(loaded && release.revision && loaded !== release.revision),
      });
    }
    await registration?.update();
  } catch {
    // Lack of connectivity must not erase an update already detected or sales.
  }
}

export async function applyAppUpdate(): Promise<void> {
  if (state.busy > 0 || state.updating) return;
  if (!navigator.onLine) {
    publish({ error: 'Conéctate a internet para descargar y aplicar la actualización.' });
    return;
  }
  publish({ updating: true, error: null });
  const activate = async () => {
    await registration?.update();
    if (registration?.installing) {
      throw new Error('La actualización se está descargando. Inténtalo de nuevo en unos segundos.');
    }
    // Cache fresh HTML before replacing its asset graph. A connection loss
    // immediately after activation must not restore HTML referencing old chunks.
    if (navigator.serviceWorker?.controller && !(await warmAppShell())) {
      throw new Error(
        'No se pudo descargar la pantalla actualizada. Comprueba la conexión e inténtalo de nuevo.',
      );
    }
    if (registration?.waiting) {
      await new Promise<void>((resolve, reject) => {
        const timeout = window.setTimeout(() => {
          navigator.serviceWorker.removeEventListener('controllerchange', done);
          reject(new Error('No se pudo activar la actualización. Inténtalo de nuevo.'));
        }, 30_000);
        const done = () => {
          clearTimeout(timeout);
          resolve();
        };
        navigator.serviceWorker.addEventListener('controllerchange', done, { once: true });
        registration!.waiting!.postMessage({ type: 'SKIP_WAITING' });
      });
    } else {
      // No worker (or already activated): refresh HTML/assets from the server.
      reloadPending = true;
      reloadWhenSafe();
    }
  };
  try {
    if (navigator.locks) {
      await navigator.locks.request(
        OPERATION_LOCK,
        { mode: 'exclusive', ifAvailable: true },
        async (lock) => {
          if (!lock)
            throw new Error('Hay un cobro o sincronización en otra pestaña. Espera a que termine.');
          await activate();
        },
      );
    } else {
      await activate();
    }
  } catch (error) {
    publish({
      updating: false,
      error: error instanceof Error ? error.message : 'No se pudo actualizar.',
    });
  }
}

export function startAppUpdates(): void {
  installUpdateAwareFetch();
  if (!import.meta.env.PROD) return;
  if ('serviceWorker' in navigator) {
    // First installation can claim the page without changing the loaded build.
    let hadController = !!navigator.serviceWorker.controller;
    navigator.serviceWorker.addEventListener('controllerchange', () => {
      if (hadController) {
        reloadPending = true;
        publish({ updating: true });
        reloadWhenSafe();
      } else {
        void warmAppShell();
      }
      hadController = true;
    });
    void navigator.serviceWorker
      .register('/sw.js', { updateViaCache: 'none' })
      .then((worker) => {
        registration = worker;
        const watchWaiting = () => {
          if (worker.waiting) publish({ available: true });
        };
        watchWaiting();
        const watchInstalling = () => {
          const installing = worker.installing;
          installing?.addEventListener('statechange', () => {
            if (installing.state === 'installed' && navigator.serviceWorker.controller) {
              publish({ available: true });
            }
            watchWaiting();
          });
        };
        watchInstalling();
        worker.addEventListener('updatefound', watchInstalling);
      })
      .catch(() => {
        /* Updates through the version endpoint still work without a worker. */
      });
  }
  void checkAppVersion();
  window.setInterval(() => void checkAppVersion(), 5 * 60 * 1000);
  window.addEventListener('online', () => void checkAppVersion());
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') void checkAppVersion();
  });
}
