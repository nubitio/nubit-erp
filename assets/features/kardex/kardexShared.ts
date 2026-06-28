import type { DataRecord } from '@nubitio/crud';

export interface StockMovementRecord extends DataRecord {
  id: string;
  warehouse: { id?: string; name?: string } | null;
  product: { id?: string; code?: string | null; name?: string; category?: { name?: string } | null } | null;
  occurredAt: string | null;
  movementType: string | null;
  movementTypeLabel: string | null;
  qtyIn: string | null;
  qtyOut: string | null;
  runningBalance: string | null;
  sourceType: string | null;
  sourceId: string | null;
  sourceReference?: string | null;
  note: string | null;
  unit?: { id?: number; name?: string } | null;
  unitCost?: string | null;
  lineValue?: string | null;
  runningValue?: string | null;
}

export type KardexValuationMethod = 'pp' | 'fifo';

export function valuationMethodLabel(method: KardexValuationMethod): string {
  return method === 'fifo' ? 'FIFO' : 'Costo prom. (PP)';
}

export function formatCurrency(value: unknown): string {
  const amount = Number(value ?? 0);
  if (!Number.isFinite(amount) || amount === 0) return '—';
  return amount.toLocaleString('es-PE', { style: 'currency', currency: 'PEN' });
}

export const MOVEMENT_TYPES = [
  { value: 'PURCHASE_IN', text: 'Compra', icon: 'ph-arrow-down-left' },
  { value: 'SALE_OUT', text: 'Venta', icon: 'ph-arrow-up-right' },
  { value: 'ADJUSTMENT', text: 'Ajuste', icon: 'ph-sliders-horizontal' },
  { value: 'TRANSFER_IN', text: 'Traslado entrada', icon: 'ph-arrow-down' },
  { value: 'TRANSFER_OUT', text: 'Traslado salida', icon: 'ph-arrow-up' },
  { value: 'MANUAL_IN', text: 'Entrada manual', icon: 'ph-plus-circle' },
  { value: 'MANUAL_OUT', text: 'Salida manual', icon: 'ph-minus-circle' },
  { value: 'VOID_REVERSAL', text: 'Reversa anulación', icon: 'ph-arrow-counter-clockwise' },
  { value: 'PURCHASE_VOID', text: 'Anulación compra', icon: 'ph-x-circle' },
  { value: 'RETURN_IN', text: 'Devolución cliente', icon: 'ph-arrow-u-down-left' },
  { value: 'RECIPE_CONSUMPTION', text: 'Consumo receta', icon: 'ph-cooking-pot' },
] as const;

const INBOUND_TYPES = new Set(['PURCHASE_IN', 'TRANSFER_IN', 'MANUAL_IN', 'VOID_REVERSAL', 'RETURN_IN']);
const OUTBOUND_TYPES = new Set(['SALE_OUT', 'TRANSFER_OUT', 'MANUAL_OUT', 'PURCHASE_VOID', 'RECIPE_CONSUMPTION']);

export type MovementTone = 'in' | 'out' | 'neutral';

export function movementTone(type: string | null | undefined): MovementTone {
  if (type && INBOUND_TYPES.has(type)) return 'in';
  if (type && OUTBOUND_TYPES.has(type)) return 'out';
  return 'neutral';
}

export function formatQty(value: unknown): string {
  if (value === null || value === undefined || value === '') return '—';
  const n = Number(value);
  return Number.isFinite(n)
    ? n.toLocaleString('es-PE', { maximumFractionDigits: 3, minimumFractionDigits: 0 })
    : '—';
}

export function formatKardexDate(value: unknown): string {
  if (!value) return '—';
  try {
    return new Date(value as string).toLocaleDateString('es-PE', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      timeZone: 'America/Lima',
    });
  } catch {
    return String(value);
  }
}

export function formatReference(row: StockMovementRecord): string {
  const resolved = row.sourceReference?.trim();
  if (resolved) return resolved;
  return '—';
}

export function extractHydraItems(raw: unknown): unknown[] {
  if (Array.isArray(raw)) return raw;
  if (raw && typeof raw === 'object') {
    const obj = raw as Record<string, unknown>;
    if (Array.isArray(obj['hydra:member'])) return obj['hydra:member'] as unknown[];
    if (Array.isArray(obj['items'])) return obj['items'] as unknown[];
    if (Array.isArray(obj['data'])) return obj['data'] as unknown[];
  }
  return [];
}

export function extractHydraTotal(raw: unknown): number {
  if (!raw || typeof raw !== 'object') return 0;
  const total = (raw as Record<string, unknown>)['hydra:totalItems'];
  return typeof total === 'number' ? total : Number(total) || 0;
}