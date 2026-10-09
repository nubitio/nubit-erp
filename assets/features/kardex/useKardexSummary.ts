import { useQuery } from '@tanstack/react-query';
import { httpRequestData } from '@shared/api/network';
import { httpClient } from '@shared/api/HttpClient';
import type { KardexValuationMethod } from './kardexShared';

export interface KardexSummaryProduct {
  code: string | null;
  name: string;
  categoryName: string | null;
  unitName: string | null;
  purchasePrice: string | null;
}

export interface KardexSummary {
  productId: string;
  warehouseId: string | null;
  valuationMethod: 'weighted_purchase_price' | 'fifo';
  product: KardexSummaryProduct;
  currentStock: string;
  stockValue: string;
  avgUnitCost: string | null;
  totalIn: string;
  totalOut: string;
  movementCount: number;
  periodFrom: string | null;
  periodTo: string | null;
  periodLabel: string;
}

async function fetchKardexSummary(
  productId: string,
  warehouseId: string | null,
  from: string | null,
  to: string | null,
  valuation: KardexValuationMethod,
): Promise<KardexSummary> {
  const params = new URLSearchParams({ productId, valuation });
  if (warehouseId) params.set('warehouseId', warehouseId);
  if (from) params.set('from', from);
  if (to) params.set('to', to);

  return httpRequestData(
    httpClient.get<KardexSummary>(`/api/inventory/kardex-summary?${params.toString()}`),
  );
}

export function useKardexSummary(
  productId: string | null,
  warehouseId: string | null,
  from: string | null,
  to: string | null,
  valuation: KardexValuationMethod = 'pp',
) {
  return useQuery({
    queryKey: ['kardex-summary', productId, warehouseId, from, to, valuation],
    queryFn: () => fetchKardexSummary(productId!, warehouseId, from, to, valuation),
    enabled: Boolean(productId),
    staleTime: 30_000,
  });
}
