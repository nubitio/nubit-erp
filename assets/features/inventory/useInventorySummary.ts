import { useQuery } from '@tanstack/react-query';
import { httpClient } from '@shared/api/HttpClient';
import { httpRequestData } from '@shared/api/network';

export interface InventorySummary {
  totalSkus: number;
  categoryCount: number;
  stockValueAtCost: string;
  lowStockCount: number;
  outOfStockCount: number;
  warehouseId: string | null;
}

async function fetchInventorySummary(
  warehouseId: string | null,
  branchId?: string | null,
): Promise<InventorySummary> {
  const params = new URLSearchParams();
  if (warehouseId) {
    params.set('warehouseId', warehouseId);
  }
  if (branchId) params.set('branchId', branchId);
  const query = params.toString();
  const url = `/api/inventory/summary${query ? `?${query}` : ''}`;
  return httpRequestData(httpClient.get<InventorySummary>(url));
}

export function useInventorySummary(warehouseId: string | null, branchId?: string | null) {
  return useQuery({
    queryKey: ['inventory-summary', branchId, warehouseId],
    queryFn: () => fetchInventorySummary(warehouseId, branchId),
    staleTime: 30_000,
  });
}
