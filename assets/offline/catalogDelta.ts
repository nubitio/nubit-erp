/**
 * Keeping the till's catalogue current without downloading it all every time.
 *
 * The server answers `GET /api/products-changes?since=<cursor>` with the products that
 * changed and the cursor for the next call. Deleted products leave no trace there, so a
 * full download still happens now and then to reconcile.
 */

/** How long a till may live on deltas alone before it downloads everything again. */
export const FULL_REFRESH_AFTER_MS = 30 * 60 * 1000;

export type CatalogSyncState = {
  /** Cursor returned by the server at the last successful sync, if any. */
  cursor: string | null;
  /** When the catalogue was last downloaded in full (ms since epoch), if ever. */
  lastFullAt: number | null;
};

export type RefreshPlan = 'full' | 'delta';

export const planCatalogRefresh = (state: CatalogSyncState, now: number): RefreshPlan => {
  if (!state.cursor || state.lastFullAt === null) return 'full';
  if (now - state.lastFullAt >= FULL_REFRESH_AFTER_MS) return 'full';
  if (state.lastFullAt > now) return 'full'; // the device clock went backwards: do not trust it
  return 'delta';
};

/** Replaces by id the products that changed and appends the ones that are new. */
export const mergeCatalogChanges = <T extends { id: string }>(current: T[], changed: T[]): T[] => {
  if (changed.length === 0) return current;

  const byId = new Map(changed.map((item) => [item.id, item]));
  const merged = current.map((item) => byId.get(item.id) ?? item);
  const known = new Set(current.map((item) => item.id));
  for (const item of changed) {
    if (!known.has(item.id)) {
      merged.push(item);
      known.add(item.id);
    }
  }
  return merged;
};

const CURSOR_KEY = 'pos_catalog_cursor';
const FULL_AT_KEY = 'pos_catalog_full_at';

export const readCatalogSyncState = (): CatalogSyncState => {
  try {
    const fullAt = Number(window.localStorage.getItem(FULL_AT_KEY));
    return {
      cursor: window.localStorage.getItem(CURSOR_KEY),
      lastFullAt: Number.isFinite(fullAt) && fullAt > 0 ? fullAt : null,
    };
  } catch {
    return { cursor: null, lastFullAt: null };
  }
};

export const writeCatalogSyncState = (state: Partial<CatalogSyncState>) => {
  try {
    if (state.cursor !== undefined) {
      if (state.cursor === null) window.localStorage.removeItem(CURSOR_KEY);
      else window.localStorage.setItem(CURSOR_KEY, state.cursor);
    }
    if (state.lastFullAt !== undefined && state.lastFullAt !== null) {
      window.localStorage.setItem(FULL_AT_KEY, String(state.lastFullAt));
    }
  } catch {
    /* private mode / quota: the next refresh simply downloads in full */
  }
};
