import './KardexPage.scss';
import { getCoreApiBaseUrl } from '@nubit/core';

const API = getCoreApiBaseUrl();
import {
  DataGridView,
  identityField,
  textField,
  datetimeField,
  entityField,
  enumField,
  type Field,
  type DataRecord,
} from '@nubit/crud';
import { Badge } from '../../shared/components/Badge';

const BASE_URL = API + 'stock-movements';

interface StockMovementRecord extends DataRecord {
  id: string;
  warehouse: { id?: string; name?: string } | null;
  product: { id?: string; code?: string | null; name?: string } | null;
  occurredAt: string | null;
  movementType: string | null;
  movementTypeLabel: string | null;
  qtyIn: string | null;
  qtyOut: string | null;
  runningBalance: string | null;
  sourceType: string | null;
  sourceId: string | null;
  note: string | null;
}

const MOVEMENT_TYPES = [
  { value: 'PURCHASE_IN', text: 'Compra' },
  { value: 'SALE_OUT', text: 'Venta' },
  { value: 'ADJUSTMENT', text: 'Ajuste' },
  { value: 'TRANSFER_IN', text: 'Traslado entrada' },
  { value: 'TRANSFER_OUT', text: 'Traslado salida' },
  { value: 'MANUAL_IN', text: 'Entrada manual' },
  { value: 'MANUAL_OUT', text: 'Salida manual' },
  { value: 'VOID_REVERSAL', text: 'Reversa anulación' },
  { value: 'PURCHASE_VOID', text: 'Anulación compra' },
  { value: 'RETURN_IN', text: 'Devolución cliente' },
  { value: 'RECIPE_CONSUMPTION', text: 'Consumo receta' },
];

const INBOUND_TYPES = new Set(['PURCHASE_IN', 'TRANSFER_IN', 'MANUAL_IN', 'VOID_REVERSAL', 'RETURN_IN']);
const OUTBOUND_TYPES = new Set(['SALE_OUT', 'TRANSFER_OUT', 'MANUAL_OUT', 'PURCHASE_VOID', 'RECIPE_CONSUMPTION']);

const movementTone = (type: string | null | undefined): 'in' | 'out' | 'neutral' => {
  if (type && INBOUND_TYPES.has(type)) return 'in';
  if (type && OUTBOUND_TYPES.has(type)) return 'out';
  return 'neutral';
};

const BADGE_VARIANT: Record<string, 'success' | 'danger' | 'secondary'> = {
  in: 'success',
  out: 'danger',
  neutral: 'secondary',
};

const formatQty = (value: unknown): string => {
  if (value === null || value === undefined || value === '') return '';
  const n = Number(value);
  return Number.isFinite(n)
    ? n.toLocaleString('es-PE', { maximumFractionDigits: 3, minimumFractionDigits: 0 })
    : '';
};

const formatProduct = (value: unknown): string => {
  if (value && typeof value === 'object') {
    const p = value as { code?: string | null; name?: string };
    return [p.code, p.name].filter(Boolean).join(' - ');
  }
  return String(value ?? '');
};

const formatWarehouse = (value: unknown): string => {
  if (value && typeof value === 'object') {
    return String((value as { name?: string }).name ?? '');
  }
  return String(value ?? '');
};

const formatMovementType = (value: unknown) => {
  if (!value) return null;
  const tone = movementTone(value as string);
  const entry = MOVEMENT_TYPES.find((t) => t.value === value);
  return <Badge variant={BADGE_VARIANT[tone]}>{entry?.text || String(value)}</Badge>;
};

const formatReference = (row: StockMovementRecord) => {
  const sourceId = row.sourceId ?? '';
  const [id, type, line] = sourceId.split(':');
  const shortId = id ? id.slice(0, 8) : '';
  const entry = MOVEMENT_TYPES.find((t) => t.value === row.movementType);
  const label = entry?.text || type || row.sourceType || 'Movimiento';

  if (!shortId) return '';

  return (
    <span className="kardex-reference" title={sourceId}>
      <span className="kardex-reference__label">{label}</span>
      <span className="kardex-reference__id">{shortId}{line ? ` · item ${line}` : ''}</span>
    </span>
  );
};

const formatNote = (value: unknown): string => {
  const note = String(value ?? '');
  if (note === 'Applied by InventoryServiceSalesInventoryPort') return 'Venta aplicada automáticamente';
  return note;
};

const formatDate = (value: unknown): string => {
  if (!value) return '';
  try {
    return new Date(value as string).toLocaleString('es-PE', {
      day: '2-digit', month: '2-digit', year: 'numeric',
      hour: '2-digit', minute: '2-digit',
      timeZone: 'America/Lima',
    });
  } catch {
    return String(value);
  }
};

const buildFields = (): Field[] => [
  identityField().build(),

  { ...textField().name('movementTypeLabel').label('').build(), visible: false, visibleOnForm: false },
  { ...textField().name('sourceType').label('').build(),      visible: false, visibleOnForm: false },

  { ...datetimeField().name('occurredAt').label('Fecha').build(),   order: 0, width: 150, formatter: (e: any) => formatDate(e.value) },
  { ...entityField(API + 'warehouses', 'id', 'name').name('warehouse').label('Almacén').build(), order: 1, width: 160, formatter: (e: any) => formatWarehouse(e.value) },
  { ...entityField(API + 'products', 'id', 'name').name('product').label('Producto').build(),   order: 2, width: 220, formatter: (e: any) => formatProduct(e.value) },
  { ...enumField(MOVEMENT_TYPES).name('movementType').label('Tipo').build(), order: 3, width: 160, formatter: (e: any) => formatMovementType(e.value) },
  { ...textField().name('sourceId').label('Referencia').build(),          order: 4, width: 170, formatter: (e: any) => formatReference(e.data as StockMovementRecord) },
  { ...textField().name('qtyIn').label('Entrada').build(),   order: 5, width: 100, align: 'right', filterable: false, formatter: (e: any) => formatQty(e.value) },
  { ...textField().name('qtyOut').label('Salida').build(),    order: 6, width: 100, align: 'right', filterable: false, formatter: (e: any) => formatQty(e.value) },
  { ...textField().name('runningBalance').label('Saldo').build(), order: 7, width: 110, align: 'right', filterable: false, formatter: (e: any) => formatQty(e.value) },
  { ...textField().name('note').label('Nota').build(), order: 8, width: 220, formatter: (e: any) => formatNote(e.value) },
];

export const KardexPage = () => {
  return (
    <div className="view-wrapper-scroll kardex-page">
      <DataGridView
        id="kardex"
        title="Kardex — Movimientos de stock"
        url={BASE_URL}
        fields={buildFields()}
        sort={[{ selector: 'occurredAt', desc: true }]}
        allowAdd={false}
        allowEdit={false}
        allowDelete={false}
      />
    </div>
  );
};
