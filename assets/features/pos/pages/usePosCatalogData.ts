import { useEffect, useState } from 'react';
import type { Subscription } from 'rxjs';
import { httpClient } from '@shared/api/HttpClient';
import { Constants } from '@platform/common/Constants';
import type { PaymentMethodOption, WarehouseOption } from './types';
import { extractCollection, isCashPaymentMethod } from './posHelpers';
import { useOfflineCatalog } from '@offline/hooks/useOfflineCatalog';

type UsePosCatalogDataParams = {
  currentBranchId: string;
};

export const usePosCatalogData = ({ currentBranchId }: UsePosCatalogDataParams) => {
  const {
    loading,
    loadError,
    categories,
    menuData,
    usingOfflineCatalog,
    lastUpdatedAt,
  } = useOfflineCatalog();
  const [paymentMethods, setPaymentMethods] = useState<PaymentMethodOption[]>([]);
  const [selectedPaymentMethodId, setSelectedPaymentMethodId] = useState<number | null>(null);
  const [warehouses, setWarehouses] = useState<WarehouseOption[]>([]);
  const [selectedWarehouseId, setSelectedWarehouseId] = useState<string>('');

  useEffect(() => {
    const subscriptions: Subscription[] = [];

    subscriptions.push(
      httpClient.get<any>(`${Constants.API_BASE_URL}payment-methods?itemsPerPage=100&page=1`).subscribe({
        next: (response) => {
          const list = extractCollection<any>(response.data).map((method) => ({
            id: Number(method.id),
            name: String(method.name ?? `Metodo ${method.id}`),
          }));
          setPaymentMethods(list);
          if (list.length > 0) {
            const cash = list.find((item) => isCashPaymentMethod(item));
            setSelectedPaymentMethodId((prev) => prev ?? cash?.id ?? list[0].id);
          }
        },
      }),
    );

    subscriptions.push(
      httpClient.get<any>(`${Constants.API_BASE_URL}warehouses?itemsPerPage=100&page=1`).subscribe({
        next: (response) => {
          const list = extractCollection<any>(response.data).map((warehouse) => ({
            id: String(warehouse.id ?? warehouse.almacen_id),
            name: String(warehouse.name ?? warehouse.almacen ?? 'Almacen'),
            branch: warehouse.branch,
          }));

          const filteredByBranch =
            currentBranchId === ''
              ? list
              : list.filter(
                  (warehouse) =>
                    String(warehouse.branch?.id ?? '').trim() === currentBranchId.trim() ||
                    String(warehouse.branch ?? '').trim() === currentBranchId.trim() ||
                    !warehouse.branch?.id,
                );

          // Fall back to full list if filtering removed everything
          const finalList = filteredByBranch.length > 0 ? filteredByBranch : list;
          setWarehouses(finalList);
          if (finalList.length > 0) {
            setSelectedWarehouseId((prev) => prev || finalList[0].id);
          }
        },
      }),
    );

    return () => {
      subscriptions.forEach((subscription) => subscription.unsubscribe());
    };
  }, [currentBranchId]);

  return {
    loading,
    loadError,
    categories,
    menuData,
    usingOfflineCatalog,
    lastUpdatedAt,
    paymentMethods,
    selectedPaymentMethodId,
    setSelectedPaymentMethodId,
    warehouses,
    selectedWarehouseId,
    setSelectedWarehouseId,
  };
};
