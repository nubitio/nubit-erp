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

async function fetchInventorySummary(warehouseId: string | null): Promise<InventorySummary> {
  const params = new URLSearchParams();
  if (warehouseId) {
    params.set('warehouseId', warehouseId);
  }
  const query = params.toString();
  const url = `/api/inventory/summary${query ? `?${query}` : ''}`;
  return httpRequestData(httpClient.get<InventorySummary>(url));
}

export function useInventorySummary(warehouseId: string | null) {
  return useQuery({
    queryKey: ['inventory-summary', warehouseId],
    queryFn: () => fetchInventorySummary(warehouseId),
    staleTime: 30_000,
  });
}
