import { httpClient } from '@shared/api/HttpClient';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { resolveEntityIri } from './inventoryEntityIri';

const API = getCoreApiBaseUrl();

export type StockAdjustOperation = 'RECEIPT' | 'ISSUE' | 'ADJUSTMENT';

export interface SubmitStockAdjustInput {
  type: StockAdjustOperation;
  warehouseIri: string;
  productIri: string;
  qty: string;
  reason?: string;
  note?: string;
  reference?: string;
}

function buildNote(note?: string, reference?: string): string | undefined {
  const parts = [reference ? `Ref: ${reference}` : '', note ?? ''].filter(Boolean);
  return parts.length > 0 ? parts.join(' — ') : undefined;
}

export async function submitStockAdjust(input: SubmitStockAdjustInput): Promise<void> {
  const warehouse = input.warehouseIri.trim();
  const product = input.productIri.trim();

  if (warehouse === '' || product === '') {
    throw new Error('Faltan el almacén o el producto para registrar el movimiento.');
  }

  const body: Record<string, unknown> = {
    type: input.type,
    warehouse,
    items: [
      {
        product,
        qty: input.qty,
      },
    ],
  };

  const note = buildNote(input.note, input.reference);
  if (note) {
    body.note = note;
  }

  if (input.type === 'ADJUSTMENT') {
    body.reason = input.reason ?? 'COUNT_CORRECTION';
  }

  await httpClient.post(`${API}stock-entries`, body);
}

export interface CycleCountLineInput {
  productIri: string;
  qty: string;
}

export async function submitCycleCount(input: {
  warehouseIri: string;
  items: CycleCountLineInput[];
  note?: string;
  reference?: string;
}): Promise<void> {
  if (input.items.length === 0) {
    throw new Error('No count lines to submit');
  }

  const body: Record<string, unknown> = {
    type: 'ADJUSTMENT',
    reason: 'COUNT_CORRECTION',
    warehouse: input.warehouseIri,
    items: input.items.map((item) => ({
      product: item.productIri,
      qty: item.qty,
    })),
  };

  const note = buildNote(input.note, input.reference);
  if (note) {
    body.note = note;
  }

  await httpClient.post(`${API}stock-entries`, body);
}

export function warehouseIriFromValue(value: unknown): string {
  return resolveEntityIri(value, 'warehouses');
}

export function productIriFromValue(value: unknown): string {
  return resolveEntityIri(value, 'products');
}
