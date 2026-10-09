import { getCoreApiBaseUrl } from '@nubitio/core';
import type { KardexValuationMethod } from './kardexShared';

const API = getCoreApiBaseUrl();

export type KardexExportFormat = 'pdf' | 'xlsx';

export function buildKardexExportUrl(
  format: KardexExportFormat,
  params: {
    productId: string;
    warehouseId?: string | null;
    from?: string | null;
    to?: string | null;
    valuation?: KardexValuationMethod;
  },
): string {
  const search = new URLSearchParams({ productId: params.productId });
  if (params.warehouseId) search.set('warehouseId', params.warehouseId);
  if (params.from) search.set('from', params.from);
  if (params.to) search.set('to', params.to);
  if (params.valuation) search.set('valuation', params.valuation);

  const suffix = format === 'pdf' ? 'export.pdf' : 'export.xlsx';
  return `${API}inventory/kardex/${suffix}?${search.toString()}`;
}

export function openKardexExport(
  format: KardexExportFormat,
  params: {
    productId: string;
    warehouseId?: string | null;
    from?: string | null;
    to?: string | null;
    valuation?: KardexValuationMethod;
  },
): void {
  window.open(buildKardexExportUrl(format, params), '_blank', 'noopener');
}
