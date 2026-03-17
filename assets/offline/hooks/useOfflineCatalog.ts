import { useEffect, useMemo, useState } from 'react';
import { httpClient } from '@shared/api/HttpClient';
import { Constants } from '@core/common/Constants';
import { useConnectivity } from '@offline/hooks/useConnectivity';
import { getCachedProducts, getLastProductsSyncAt, persistProducts } from '@offline/catalog';
import type { Category, PosItem } from '@features/pos/pages/types';
import { extractCollection, normalizeProduct } from '@features/pos/pages/posHelpers';

export const useOfflineCatalog = () => {
  const { isOnline } = useConnectivity();
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [menuData, setMenuData] = useState<PosItem[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [lastUpdatedAt, setLastUpdatedAt] = useState<string | null>(null);
  const [usingOfflineCatalog, setUsingOfflineCatalog] = useState(false);

  useEffect(() => {
    const loadFromOffline = async () => {
      const cachedRows = await getCachedProducts();
      const products = cachedRows.map((row) => normalizeProduct(row.raw));
      setMenuData(products);
      setCategories(
        Array.from(
          products.reduce((map, product) => map.set(product.category.id, product.category), new Map<string, Category>()).values(),
        ),
      );
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
      const subscription = httpClient.get<any>(`${Constants.API_BASE_URL}products?itemsPerPage=9999`).subscribe({
        next: async (response) => {
          const rawProducts = extractCollection<any>(response.data);
          const normalizedProducts = rawProducts.map(normalizeProduct);
          const categoryMap = new Map<string, Category>();
          normalizedProducts.forEach((product) => {
            categoryMap.set(product.category.id, product.category);
          });

          setMenuData(normalizedProducts);
          setCategories(Array.from(categoryMap.values()));
          setUsingOfflineCatalog(false);
          setLoading(false);
          await persistProducts(rawProducts);
          setLastUpdatedAt(await getLastProductsSyncAt());
        },
        error: async () => {
          await loadFromOffline();
          setLoadError('Mostrando catálogo offline: no se pudo actualizar desde el servidor.');
        },
      });
      return () => subscription.unsubscribe();
    };

    if (isOnline) {
      const cleanup = loadFromApi();
      return cleanup;
    }

    void loadFromOffline();
    return;
  }, [isOnline]);

  return useMemo(
    () => ({
      loading,
      loadError,
      menuData,
      categories,
      usingOfflineCatalog,
      lastUpdatedAt,
    }),
    [categories, lastUpdatedAt, loadError, loading, menuData, usingOfflineCatalog],
  );
};
