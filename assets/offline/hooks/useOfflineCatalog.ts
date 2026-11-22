import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { httpClient } from '@shared/api/HttpClient';
import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();
import { useConnectivity } from '@offline/hooks/useConnectivity';
import {
  getCachedProducts,
  getLastProductsSyncAt,
  mergeCachedProducts,
  persistProducts,
  updateCachedProductPrice,
} from '@offline/catalog';
import {
  mergeCatalogChanges,
  planCatalogRefresh,
  readCatalogSyncState,
  writeCatalogSyncState,
} from '@offline/catalogDelta';
import { toMoneyString } from '@shared/pos/helpers';
import type { Category, PosItem } from '@shared/pos/types';
import { extractCollection, normalizeProduct } from '@shared/pos/helpers';

type ProductApiPayload = Record<string, unknown>;

export const useOfflineCatalog = () => {
  const { isOnline } = useConnectivity();
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [menuData, setMenuData] = useState<PosItem[]>([]);
  const categories = useMemo(
    () =>
      Array.from(
        menuData
          .reduce(
            (map, product) => map.set(product.category.id, product.category),
            new Map<string, Category>(),
          )
          .values(),
      ),
    [menuData],
  );
  const [lastUpdatedAt, setLastUpdatedAt] = useState<string | null>(null);
  const [usingOfflineCatalog, setUsingOfflineCatalog] = useState(false);
  const refreshingRef = useRef(false);

  /**
   * Downloads the whole catalogue. The cursor is asked for BEFORE the download, so a
   * product that changes while it runs is picked up by the next delta, not lost.
   */
  const downloadAll = useCallback(async (signal?: AbortSignal) => {
    let cursor: string | null = null;
    try {
      const probe = await httpClient.get<{ cursor?: string }>(`${API}products-changes`, { signal });
      cursor = probe.data?.cursor ?? null;
    } catch {
      /* no delta support reachable: the download below still works, the next refresh is full */
    }

    const response = await httpClient.get<unknown>(`${API}products?itemsPerPage=9999`, { signal });
    const rawProducts = extractCollection<ProductApiPayload>(response.data);
    const normalizedProducts = rawProducts.map(normalizeProduct);

    setMenuData(normalizedProducts);
    setUsingOfflineCatalog(false);
    setLoading(false);
    await persistProducts(rawProducts);
    writeCatalogSyncState({ cursor, lastFullAt: Date.now() });
    setLastUpdatedAt(await getLastProductsSyncAt());
  }, []);

  /** Applies only what changed since the last sync. Throws if the server cannot answer it. */
  const applyDelta = useCallback(async (since: string) => {
    const response = await httpClient.get<{ cursor: string; products: ProductApiPayload[] }>(
      `${API}products-changes`,
      { params: { since } },
    );
    const { cursor, products } = response.data;
    if (products.length > 0) {
      const changed = products.map(normalizeProduct);
      setMenuData((current) => mergeCatalogChanges(current, changed));
      await mergeCachedProducts(products);
    }
    writeCatalogSyncState({ cursor });
    setUsingOfflineCatalog(false);
    setLastUpdatedAt(await getLastProductsSyncAt());
  }, []);

  /**
   * Brings the catalogue up to date without the loading state. Called when a price
   * changes (Mercure), on the polling backup, and when the tab or the connection
   * returns. It asks only for what changed, and downloads everything now and then to
   * reconcile (deleted products leave no trace). A failed refresh keeps what is on
   * screen: it must never blank the till or raise the "offline" banner on a blip.
   */
  const refresh = useCallback(async () => {
    if (refreshingRef.current) return;
    refreshingRef.current = true;
    try {
      const state = readCatalogSyncState();
      if (planCatalogRefresh(state, Date.now()) === 'delta' && state.cursor) {
        try {
          await applyDelta(state.cursor);
          return;
        } catch {
          /* an unusable cursor or a failed call: fall back to the full download */
        }
      }
      await downloadAll();
    } catch {
      /* keep the catalogue already loaded */
    } finally {
      refreshingRef.current = false;
    }
  }, [applyDelta, downloadAll]);

  useEffect(() => {
    const loadFromOffline = async () => {
      const cachedRows = await getCachedProducts();
      const products = cachedRows.map((row) => normalizeProduct(row.raw));
      setMenuData(products);
      setLastUpdatedAt(await getLastProductsSyncAt());
      setUsingOfflineCatalog(true);
      setLoading(false);
      if (products.length === 0) {
        setLoadError('No hay catálogo offline disponible. Conéctate para sincronizar productos.');
      }
    };

    const loadFromApi = () => {
      setLoading(true);
      setLoadError(null);
      const controller = new AbortController();
      downloadAll(controller.signal).catch(() => {
        if (controller.signal.aborted) return;
        setLoadError('Mostrando catálogo offline: no se pudo actualizar desde el servidor.');
      });
      return () => {
        controller.abort();
      };
    };

    if (isOnline) {
      const cleanup = loadFromApi();
      return cleanup;
    }

    void loadFromOffline();
    return;
  }, [isOnline, downloadAll]);

  const updateProductPrice = useCallback((productId: string, salePrice: string | number) => {
    const normalizedPrice = toMoneyString(salePrice);
    setMenuData((prev) =>
      prev.map((product) =>
        product.id === productId ? { ...product, salePrice: normalizedPrice } : product,
      ),
    );
    void updateCachedProductPrice(productId, normalizedPrice);
  }, []);

  return useMemo(
    () => ({
      loading,
      loadError,
      menuData,
      categories,
      usingOfflineCatalog,
      lastUpdatedAt,
      updateProductPrice,
      refresh,
    }),
    [
      refresh,
      categories,
      lastUpdatedAt,
      loadError,
      loading,
      menuData,
      updateProductPrice,
      usingOfflineCatalog,
    ],
  );
};
