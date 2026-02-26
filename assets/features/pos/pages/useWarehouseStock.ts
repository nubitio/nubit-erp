import { useCallback, useEffect, useRef, useState } from 'react';
import { httpClient } from '@shared/api/HttpClient';
import { Constants } from '@platform/common/Constants';
import type { CartItem, PosItem } from './types';
import { toOptionalNumber } from './posHelpers';

type WarehouseStockMap = Record<string, number | undefined>;

export const useWarehouseStock = (selectedWarehouseId: string, cart: CartItem[], visibleProducts: PosItem[]) => {
  const [warehouseStockByProductId, setWarehouseStockByProductId] = useState<WarehouseStockMap>({});
  const stockRequestsRef = useRef<Map<string, Promise<number | undefined>>>(new Map());

  const getWarehouseStockCacheKey = (warehouseId: string, productId: string) => `${warehouseId}:${productId}`;

  const fetchWarehouseStock = useCallback(
    (productId: string, warehouseId: string): Promise<number | undefined> => {
      if (!warehouseId) {
        return Promise.resolve(undefined);
      }

      const cached = warehouseStockByProductId[productId];
      if (cached !== undefined) {
        return Promise.resolve(cached);
      }

      const requestKey = getWarehouseStockCacheKey(warehouseId, productId);
      const inFlight = stockRequestsRef.current.get(requestKey);
      if (inFlight) {
        return inFlight;
      }

      const request = new Promise<number | undefined>((resolve) => {
        httpClient
          .get<any>(`${Constants.API_BASE_URL}products/${productId}/stock/${warehouseId}`)
          .subscribe({
            next: (response) => {
              const stock = toOptionalNumber(response.data?.stock);
              setWarehouseStockByProductId((prev) => ({ ...prev, [productId]: stock ?? 0 }));
              resolve(stock ?? 0);
            },
            error: () => resolve(undefined),
          })
          .add(() => {
            stockRequestsRef.current.delete(requestKey);
          });
      });

      stockRequestsRef.current.set(requestKey, request);
      return request;
    },
    [warehouseStockByProductId],
  );

  useEffect(() => {
    setWarehouseStockByProductId({});
  }, [selectedWarehouseId]);

  useEffect(() => {
    if (!selectedWarehouseId) {
      return;
    }

    const productIds = new Set<string>();
    cart.forEach((item) => productIds.add(item.id));
    visibleProducts.slice(0, 24).forEach((item) => productIds.add(item.id));

    productIds.forEach((productId) => {
      if (warehouseStockByProductId[productId] === undefined) {
        void fetchWarehouseStock(productId, selectedWarehouseId);
      }
    });
  }, [selectedWarehouseId, cart, visibleProducts, warehouseStockByProductId, fetchWarehouseStock]);

  const getEffectiveStock = useCallback(
    (item: PosItem) => {
      if (!selectedWarehouseId) {
        return item.stock;
      }
      return warehouseStockByProductId[item.id] ?? item.stock;
    },
    [selectedWarehouseId, warehouseStockByProductId],
  );

  return {
    fetchWarehouseStock,
    getEffectiveStock,
    warehouseStockByProductId,
  };
};
