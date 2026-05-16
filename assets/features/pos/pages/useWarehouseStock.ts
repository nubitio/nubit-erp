import { useCallback, useEffect, useRef, useState } from 'react';
import { httpClient } from '@shared/api/HttpClient';
import { Constants } from '@core/common/Constants';
import type { CartItem, PosItem } from './types';
import { toOptionalNumber } from './posHelpers';

type WarehouseStockMap = Record<string, number | undefined>;

export const useWarehouseStock = (
  selectedWarehouseId: string,
  cart: CartItem[],
  visibleProducts: PosItem[],
) => {
  const [warehouseStockByProductId, setWarehouseStockByProductId] = useState<WarehouseStockMap>({});
  const stockRequestsRef = useRef<Map<string, Promise<number | undefined>>>(new Map());

  const getWarehouseStockCacheKey = (warehouseId: string, productId: string) =>
    `${warehouseId}:${productId}`;

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

      const request = httpClient
        .get<any>(`${Constants.API_BASE_URL}products/${productId}/stock/${warehouseId}`)
        .then((response) => {
            const stock = toOptionalNumber(response.data?.stock);
            setWarehouseStockByProductId((prev) => ({ ...prev, [productId]: stock ?? 0 }));
            stockRequestsRef.current.delete(requestKey);
            return stock ?? 0;
          })
        .catch(() => {
            stockRequestsRef.current.delete(requestKey);
            return undefined;
          });

      stockRequestsRef.current.set(requestKey, request);
      return request;
    },
    [warehouseStockByProductId],
  );

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- resetting warehouse stock map when warehouse selection changes; intentional state reset
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
