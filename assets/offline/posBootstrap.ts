import { dehydrate, hydrate, type QueryClient } from '@tanstack/react-query';
import { subscribeToAuthSessionSync } from '@shared/lib/authSessionSync';
import updatePolicy from '../../config/app-update.json';

export const POS_BOOTSTRAP_STORAGE_KEY = 'efact_pos_bootstrap_v1';

// Persist only read-only bootstrap queries, never credentials or business rows.
const BOOTSTRAP_QUERIES = new Set([
  'tenant-runtime-config',
  'api-doc-discovery',
  'pos-payment-methods',
  'warehouses',
  // The user's own open session, so the POS can start offline; revalidated as soon as it reconnects.
  'cash-pos-state',
]);

function getScope(): string | null {
  try {
    const user = JSON.parse(localStorage.getItem('user') ?? 'null');
    const branch = JSON.parse(localStorage.getItem('selectedBranchId') ?? 'null');
    if (!user?.username || !branch) return null;
    return JSON.stringify([
      window.location.origin,
      user.userId ?? user.username,
      user.username,
      branch,
      user.role ?? null,
      user.roles ?? [],
      user.permissions ?? [],
    ]);
  } catch {
    return null;
  }
}

/**
 * Keep bootstrap metadata for reloads in this tab's committed auth session.
 * Restored queries are stale so reconnect always revalidates against the API.
 * Changing user, permissions or branch discards the previous snapshot/cache.
 */
export function installPosBootstrapCache(client: QueryClient): () => void {
  let scope = getScope();
  let restoring = false;
  const isBootstrap = (query: { queryKey: readonly unknown[] }) =>
    BOOTSTRAP_QUERIES.has(String(query.queryKey[0]));

  const restore = () => {
    try {
      const snapshot = JSON.parse(sessionStorage.getItem(POS_BOOTSTRAP_STORAGE_KEY) ?? 'null');
      if (
        !scope ||
        snapshot?.scope !== scope ||
        snapshot?.contractVersion !== updatePolicy.offlineContractVersion ||
        !Array.isArray(snapshot?.state?.queries)
      ) {
        sessionStorage.removeItem(POS_BOOTSTRAP_STORAGE_KEY);
        return;
      }
      restoring = true;
      hydrate(client, {
        mutations: [],
        queries: snapshot.state.queries.filter(isBootstrap).map((query: { state: object }) => ({
          ...query,
          state: { ...query.state, dataUpdatedAt: 0, fetchStatus: 'idle' },
        })),
      });
    } catch {
      // Disabled/quota-limited storage must not prevent normal online startup.
    } finally {
      restoring = false;
    }
  };

  const syncScope = () => {
    const next = getScope();
    if (next === scope) return;
    scope = next;
    restoring = true;
    client.removeQueries({ predicate: isBootstrap });
    restoring = false;
    try {
      sessionStorage.removeItem(POS_BOOTSTRAP_STORAGE_KEY);
    } catch {
      // Storage may be unavailable in private browsing.
    }
  };

  restore();
  const unsubscribeCache = client.getQueryCache().subscribe((event) => {
    if (restoring) return;
    syncScope();
    if (!scope || event.type !== 'updated' || event.action.type !== 'success') return;
    if (!isBootstrap(event.query)) return;
    try {
      const state = dehydrate(client, {
        shouldDehydrateMutation: () => false,
        shouldDehydrateQuery: (query) => isBootstrap(query) && query.state.status === 'success',
      });
      sessionStorage.setItem(
        POS_BOOTSTRAP_STORAGE_KEY,
        JSON.stringify({ scope, contractVersion: updatePolicy.offlineContractVersion, state }),
      );
    } catch {
      // In-memory query results remain available if persistence fails.
    }
  });
  const subscription = subscribeToAuthSessionSync(syncScope);
  window.addEventListener('storage', syncScope);

  return () => {
    unsubscribeCache();
    subscription.unsubscribe();
    window.removeEventListener('storage', syncScope);
  };
}
