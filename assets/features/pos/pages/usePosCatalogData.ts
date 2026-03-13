import { firstValueFrom } from 'rxjs';
import { useEffect, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { httpClient } from '@shared/api/HttpClient';
import { Constants } from '@platform/common/Constants';
import type { PaymentMethodOption, WarehouseOption } from './types';
import { extractCollection, isCashPaymentMethod } from './posHelpers';
import { useOfflineCatalog } from '@offline/hooks/useOfflineCatalog';

type UsePosCatalogDataParams = {
  currentBranchId: string;
};

// ─── Payment Methods ──────────────────────────────────────────────────────────

// eslint-disable-next-line @typescript-eslint/no-explicit-any -- API Platform response shape varies
const mapPaymentMethods = (responseData: any): PaymentMethodOption[] =>
  extractCollection<any>(responseData).map((method) => ({
    id: Number(method.id),
    name: String(method.name ?? `Metodo ${method.id}`),
  }));

export function usePosPaymentMethods() {
  return useQuery<PaymentMethodOption[], Error>({
    queryKey: ['pos-payment-methods'],
    queryFn: () =>
      firstValueFrom(
        // eslint-disable-next-line @typescript-eslint/no-explicit-any -- API Platform response shape varies
        httpClient.get<any>(`${Constants.API_BASE_URL}payment-methods?itemsPerPage=100&page=1`),
      ).then((response) => mapPaymentMethods(response.data)),
    staleTime: 5 * 60 * 1000,
    placeholderData: [],
  });
}

// ─── Warehouses ───────────────────────────────────────────────────────────────

// eslint-disable-next-line @typescript-eslint/no-explicit-any -- API Platform response shape varies
const mapWarehouses = (responseData: any): WarehouseOption[] =>
  extractCollection<any>(responseData).map((warehouse) => ({
    id: String(warehouse.id ?? warehouse.almacen_id),
    name: String(warehouse.name ?? warehouse.almacen ?? 'Almacen'),
    branch: warehouse.branch,
  }));

export function usePosWarehouses(currentBranchId: string) {
  return useQuery<WarehouseOption[], Error>({
    queryKey: ['pos-warehouses', currentBranchId],
    queryFn: () =>
      firstValueFrom(
        // eslint-disable-next-line @typescript-eslint/no-explicit-any -- API Platform response shape varies
        httpClient.get<any>(`${Constants.API_BASE_URL}warehouses?itemsPerPage=100&page=1`),
      ).then((response) => {
        const list = mapWarehouses(response.data);
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
        return filteredByBranch.length > 0 ? filteredByBranch : list;
      }),
    staleTime: 5 * 60 * 1000,
    placeholderData: [],
  });
}

// ─── Composite hook (used by POSPage) ─────────────────────────────────────────

export const usePosCatalogData = ({ currentBranchId }: UsePosCatalogDataParams) => {
  const { loading, loadError, categories, menuData, usingOfflineCatalog, lastUpdatedAt } =
    useOfflineCatalog();

  // ── Payment methods ──────────────────────────────────────────────────────────
  const { data: paymentMethods = [] } = usePosPaymentMethods();
  const [selectedPaymentMethodId, setSelectedPaymentMethodId] = useState<number | null>(null);

  // Auto-select the cash method (or first) when payment methods arrive
  useEffect(() => {
    if (paymentMethods.length > 0 && selectedPaymentMethodId === null) {
      const cash = paymentMethods.find((item) => isCashPaymentMethod(item));
      setSelectedPaymentMethodId(cash?.id ?? paymentMethods[0].id);
    }
    // Only react to changes in the list, not the controlled selection
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [paymentMethods]);

  // ── Warehouses ───────────────────────────────────────────────────────────────
  const { data: warehouses = [] } = usePosWarehouses(currentBranchId);
  const [selectedWarehouseId, setSelectedWarehouseId] = useState<string>('');

  // Auto-select first warehouse once data arrives
  useEffect(() => {
    if (warehouses.length > 0 && selectedWarehouseId === '') {
      setSelectedWarehouseId(warehouses[0].id);
    }
    // Only react to warehouse list changes
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [warehouses]);

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
