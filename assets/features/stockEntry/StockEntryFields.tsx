import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();
import { Field } from '@nubitio/crud';
import {
  identityField,
  entityField,
  enumField,
  numberField,
  textareaField,
  textField,
} from '@nubitio/crud';
import { SharedUtils } from '@shared/lib/SharedUtils';
import type { GridCellContext } from '@nubitio/crud';
import type { TenantRuntimeConfig } from '@shared/hooks';
import { activeOnlyFilter } from '@shared/lib/activeEntityField';

// ─── Shared types ─────────────────────────────────────────────────────────────

export type StockEntryMode = 'receipt' | 'issue' | 'transfer' | 'adjustment';

export interface StockEntryPrefill {
  warehouseId?: string;
  productId?: string;
}

const REASON_OPTIONS = [
  { value: 'INITIAL_STOCK',    text: 'Carga inicial' },
  { value: 'COUNT_CORRECTION', text: 'Corrección por conteo' },
  { value: 'LOSS',             text: 'Pérdida / merma' },
  { value: 'DAMAGE',           text: 'Producto dañado' },
  { value: 'THEFT',            text: 'Robo / sustracción' },
  { value: 'EXPIRY',           text: 'Producto vencido' },
  { value: 'OTHER',            text: 'Otro' },
];

const TYPE_MAP: Record<StockEntryMode, string> = {
  receipt:    'RECEIPT',
  issue:      'ISSUE',
  transfer:   'TRANSFER',
  adjustment: 'ADJUSTMENT',
};

const OPERATION_TYPE_MAP: Record<StockEntryMode, number> = {
  receipt: 1,
  issue:   2,
  transfer: 1,
  adjustment: 1,
};

// ─── Header fields (common across all types) ──────────────────────────────────

export const StockEntryFields = (
  mode: StockEntryMode,
  branchId: string | number = '',
  runtimeConfig?: TenantRuntimeConfig,
  prefill?: StockEntryPrefill,
): Field[] => {
  const fields: Field[] = [];
  const defaultWarehouseId = prefill?.warehouseId ?? runtimeConfig?.defaults.warehouse?.id ?? '';
  const showWarehouseSelector = runtimeConfig?.ui.inventory.showWarehouseSelector ?? true;

  fields.push(identityField().build());

  // Hidden type field — always pre-filled based on page mode
  fields.push(
    textField()
      .name('type')
      .label('Tipo')
      .defaultValue(TYPE_MAP[mode])
      .hidden(true)
      .visible(false)
      .visibleOnForm(false)
      .build(),
  );

  // Source warehouse
  const warehouseField = entityField(API + 'warehouses', 'id', 'name')
    .name('warehouse')
    .label(mode === 'transfer' ? 'Almacén origen' : 'Almacén')
    .filters([{ field: 'branch', operator: '=', value: branchId }, ...activeOnlyFilter()])
    .required(true);

  if (!showWarehouseSelector && defaultWarehouseId) {
    warehouseField
      .defaultValue(`${API}warehouses/${defaultWarehouseId}`)
      .hidden(true)
      .visible(false)
      .visibleOnForm(false);
  }

  fields.push(warehouseField.build());

  // Target warehouse — only for TRANSFER
  if (mode === 'transfer') {
    fields.push(
      entityField(API + 'warehouses', 'id', 'name')
        .name('targetWarehouse')
        .label('Almacén destino')
        .filters([{ field: 'branch', operator: '=', value: branchId }, ...activeOnlyFilter()])
        .required(true)
        .build(),
    );
  }

  // Operation category — for RECEIPT and ISSUE
  if (mode === 'receipt' || mode === 'issue') {
    fields.push(
      entityField(API + 'logistic-operations', 'id', 'name')
        .name('operation')
        .label('Tipo de operación')
        .filters([{ field: 'type', operator: '=', value: OPERATION_TYPE_MAP[mode] }])
        .build(),
    );
  }

  // Reason — only for ADJUSTMENT
  if (mode === 'adjustment') {
    fields.push(
      enumField(REASON_OPTIONS)
        .name('reason')
        .label('Razón')
        .required(true)
        .build(),
    );
  }

  fields.push(
    textareaField()
      .name('note')
      .label('Nota')
      .build(),
  );

  // Read-only grid columns
  fields.push(
    textField()
      .name('typeLabel')
      .label('Tipo')
      .readonly(true)
      .visibleOnForm(false)
      .build(),
  );

  fields.push(
    textField()
      .name('createdAt')
      .label('Fecha')
      .readonly(true)
      .visibleOnForm(false)
      .formatter((cell: GridCellContext) => SharedUtils.dateFormatter(cell.value as string | Date | null | undefined))
      .build(),
  );

  return fields;
};

// ─── Item fields ──────────────────────────────────────────────────────────────

export const StockEntryItemFields = (mode: StockEntryMode, prefill?: StockEntryPrefill): Field[] => {
  const fields: Field[] = [];

  fields.push(identityField().build());

  const productField = entityField(API + 'products', 'id', 'name')
    .name('product')
    .label('Producto')
    .required(true);

  if (prefill?.productId) {
    productField.defaultValue(`${API}products/${prefill.productId}`);
  }

  fields.push(productField.build());

  fields.push(
    numberField()
      .name('qty')
      .label(mode === 'adjustment' ? 'Stock real (cantidad física)' : 'Cantidad')
      .required(true)
      .defaultValue(mode === 'adjustment' ? 0 : 1)
      .build(),
  );

  // For ADJUSTMENT — show before/delta as read-only in grid
  if (mode === 'adjustment') {
    fields.push(
      numberField()
        .name('qtyBefore')
        .label('Stock anterior')
        .readonly(true)
        .visibleOnForm(false)
        .build(),
    );

    fields.push(
      numberField()
        .name('qtyDelta')
        .label('Diferencia')
        .readonly(true)
        .visibleOnForm(false)
        .build(),
    );
  }

  fields.push(
    entityField(API + 'unit-of-measures', 'id', 'name')
      .name('unit')
      .label('Unidad')
      .build(),
  );

  fields.push(
    textareaField()
      .name('note')
      .label('Nota')
      .build(),
  );

  return fields;
};
