import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { httpClient } from '@shared/api/HttpClient';
import { httpRequestData } from '@shared/api/network';
import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();
import type { PaymentMethodOption, WarehouseOption } from './types';
import { extractCollection, isCashPaymentMethod } from './posHelpers';
import { useOfflineCatalog } from '@offline/hooks/useOfflineCatalog';
import { useWarehouses } from '@shared/hooks/useWarehouses';
import type { TenantRuntimeConfig } from '@shared/hooks';

type UsePosCatalogDataParams = {
  currentBranchId: string;
  runtimeConfig?: TenantRuntimeConfig;
};

// ─── Payment Methods ──────────────────────────────────────────────────────────

type PaymentMethodResponse = { id?: string | number; name?: string | null };

const mapPaymentMethods = (responseData: unknown): PaymentMethodOption[] =>
  extractCollection<PaymentMethodResponse>(responseData).map((method) => ({
    id: Number(method.id),
    name: String(method.name ?? `Metodo ${method.id ?? ''}`),
  }));

export function usePosPaymentMethods() {
  return useQuery<PaymentMethodOption[], Error>({
    queryKey: ['pos-payment-methods'],
    queryFn: () =>
      httpRequestData<unknown>(
        httpClient.get<unknown>(
          `${API}payment-methods?itemsPerPage=100&page=1`,
        ),
      ).then((responseData) => mapPaymentMethods(responseData)),
    staleTime: 5 * 60 * 1000,
    placeholderData: [],
  });
}

// ─── Warehouses ───────────────────────────────────────────────────────────────

export function usePosWarehouses(currentBranchId: string, enabled = true) {
  // Delegate the actual fetch to the shared hook
  const { warehouses: sharedWarehouses, isLoading } = useWarehouses({
    branchId: currentBranchId || undefined,
    enabled,
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

export const usePosCatalogData = ({ currentBranchId, runtimeConfig }: UsePosCatalogDataParams) => {
  const {
    loading,
    loadError,
    categories,
    menuData,
    usingOfflineCatalog,
    lastUpdatedAt,
    updateProductPrice,
  } = useOfflineCatalog();

  // ── Payment methods ──────────────────────────────────────────────────────────
  const { data: paymentMethods = [] } = usePosPaymentMethods();
  const [selectedPaymentMethodOverrideId, setSelectedPaymentMethodId] = useState<number | null>(null);
  const defaultPaymentMethodId = paymentMethods.length > 0
    ? (paymentMethods.find((item) => isCashPaymentMethod(item))?.id ?? paymentMethods[0].id)
    : null;
  const selectedPaymentMethodId = selectedPaymentMethodOverrideId ?? defaultPaymentMethodId;

  // ── Warehouses ───────────────────────────────────────────────────────────────
  const defaultWarehouseId = runtimeConfig?.defaults.warehouse?.id ?? '';
  const defaultWarehouse = runtimeConfig?.defaults.warehouse;
  const shouldFetchWarehouses =
    runtimeConfig?.ui.inventory.showWarehouseSelector !== false || !defaultWarehouseId;
  const { data: fetchedWarehouses = [] } = usePosWarehouses(
    currentBranchId,
    shouldFetchWarehouses,
  );
  const warehouses = shouldFetchWarehouses
    ? fetchedWarehouses
    : defaultWarehouse
      ? [{ id: defaultWarehouse.id, name: defaultWarehouse.name }]
      : [];
  const [selectedWarehouseOverrideId, setSelectedWarehouseId] = useState<string>('');
  const selectedWarehouseId = selectedWarehouseOverrideId || defaultWarehouseId || warehouses[0]?.id || '';

  return {
    loading,
    loadError,
    categories,
    menuData,
    usingOfflineCatalog,
    lastUpdatedAt,
    updateProductPrice,
    paymentMethods,
    selectedPaymentMethodId,
    setSelectedPaymentMethodId,
    warehouses,
    selectedWarehouseId,
    setSelectedWarehouseId,
  };
};
