import { useEffect, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { httpClient } from '@shared/api/HttpClient';
import { httpRequestData } from '@shared/api/network';
import { getCoreApiBaseUrl } from '@nubit/core';

const API = getCoreApiBaseUrl();
import type { PaymentMethodOption, WarehouseOption } from './types';
import { extractCollection, isCashPaymentMethod } from './posHelpers';
import { useOfflineCatalog } from '@offline/hooks/useOfflineCatalog';
import { useWarehouses } from '@shared/hooks/useWarehouses';

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
      httpRequestData<any>(
        httpClient.get<any>(
          `${API}payment-methods?itemsPerPage=100&page=1`,
        ),
      ).then((responseData) => mapPaymentMethods(responseData)),
    staleTime: 5 * 60 * 1000,
    placeholderData: [],
  });
}

// ─── Warehouses ───────────────────────────────────────────────────────────────

export function usePosWarehouses(currentBranchId: string) {
  // Delegate the actual fetch to the shared hook
  const { warehouses: sharedWarehouses, isLoading } = useWarehouses({
    branchId: currentBranchId || undefined,
    enabled: true,
  });

  // Convert shared shape → legacy POS shape and apply the same fallback filtering
  // that the old implementation had.
  const legacyWarehouses: WarehouseOption[] = sharedWarehouses.map((w) => ({
    id: w.id,
    name: w.name,
    branch: w.branchId ? { id: w.branchId } : undefined,
  }));

  const filteredByBranch =
    currentBranchId === ''
      ? legacyWarehouses
      : legacyWarehouses.filter(
          (warehouse) =>
            String(warehouse.branch?.id ?? '').trim() === currentBranchId.trim() ||
            String(warehouse.branch ?? '').trim() === currentBranchId.trim() ||
            !warehouse.branch?.id,
        );

  // Fall back to full list if filtering removed everything (exact same behavior as before)
  const finalList =
    filteredByBranch.length > 0 ? filteredByBranch : legacyWarehouses;

  // We still return a useQuery-like shape for backward compatibility with the callers
  return {
    data: finalList,
    isLoading,
    error: null,
  } as const;
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
