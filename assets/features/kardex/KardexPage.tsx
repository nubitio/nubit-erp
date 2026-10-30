import './KardexPage.scss';
import '@features/inventory/InventoryHub.scss';
import { useCallback, useMemo } from 'react';
import { useLocation, useSearchParams } from 'react-router-dom';
import { getCoreApiBaseUrl } from '@nubitio/core';
import {
  ColumnChooserButton,
  DataGridView,
  identityField,
  textField,
  datetimeField,
  entityField,
  enumField,
  useColumnChooser,
  type ColumnGroupDef,
  type Field,
  type GridCellContext,
  type ResourceConfig,
} from '@nubitio/crud';
import { useCoreTranslation } from '@nubitio/core';
import { useSessionInfo } from '@shared/sessionInfo';
import { useWarehouses } from '@shared/hooks/useWarehouses';
import { KardexDashboard } from './KardexDashboard';
import { KardexMovementTypeCell } from './KardexMovementTypeCell';
import {
  MOVEMENT_TYPES,
  formatCurrency,
  formatKardexDate,
  formatQty,
  formatReference,
  type KardexValuationMethod,
  type StockMovementRecord,
} from './kardexShared';
import { openKardexExport } from './kardexExport';

const API = getCoreApiBaseUrl();
const BASE_URL = API + 'stock-movements';

const KARDEX_COLUMN_GROUP_DEFS: ColumnGroupDef[] = [
  { key: 'document', label: 'DOCUMENTO', align: 'left' },
  { key: 'entries', label: 'ENTRADAS', className: 'kardex-group--in' },
  { key: 'exits', label: 'SALIDAS', className: 'kardex-group--out' },
  { key: 'balance', label: 'SALDO' },
];

const KARDEX_COLUMN_PRESETS = [
  {
    key: 'valorizado',
    label: 'Valorizado',
    columns: [
      'occurredAt',
      'movementType',
      'qtyIn',
      'unitCost',
      'lineValue',
      'qtyOut',
      '_unitCostExit',
      '_lineValueExit',
      'runningBalance',
      'runningValue',
    ],
  },
  {
    key: 'ledger',
    label: 'Cantidades',
    columns: ['occurredAt', 'movementType', 'qtyIn', 'qtyOut', 'runningBalance'],
  },
  {
    key: 'full',
    label: 'Completo',
    columns: [
      'occurredAt',
      'warehouse',
      'product',
      'movementType',
      'qtyIn',
      'qtyOut',
      'runningBalance',
      'note',
    ],
  },
];

const KARDEX_PRESET_RESOURCE: ResourceConfig = {
  id: 'kardex',
  title: 'Kardex',
  apiUrl: BASE_URL,
  defaultPreset: 'valorizado',
  columnPresets: KARDEX_COLUMN_PRESETS,
};

type FormatterEvent = GridCellContext;

const rowOf = (e: FormatterEvent): StockMovementRecord => e.data as StockMovementRecord;

const formatNote = (value: unknown): string => {
  const note = String(value ?? '');
  if (note === 'Applied by InventoryServiceSalesInventoryPort')
    return 'Venta aplicada automáticamente';
  return note;
};

const formatUnitCost = (e: FormatterEvent) => formatCurrency(e.value);
const formatLineValueForIn = (e: FormatterEvent) => {
  const row = rowOf(e);
  if (!row?.qtyIn) return '—';
  return formatCurrency(row.lineValue);
};
const formatLineValueForOut = (e: FormatterEvent) => {
  const row = rowOf(e);
  if (!row?.qtyOut) return '—';
  return formatCurrency(row.lineValue);
};

const buildFields = (scoped: boolean, showValuation: boolean): Field[] => [
  identityField().build(),
  {
    ...textField().name('movementTypeLabel').label('').build(),
    visible: false,
    visibleOnForm: false,
  },
  { ...textField().name('sourceType').label('').build(), visible: false, visibleOnForm: false },

  {
    ...datetimeField().name('occurredAt').label('Fecha').build(),
    columnGroup: 'document',
    order: 0,
    width: 110,
    formatter: (e: FormatterEvent) => formatKardexDate(e.value),
  },
  {
    ...enumField(MOVEMENT_TYPES.map(({ value, text }) => ({ value, text })))
      .name('movementType')
      .label('Movimiento')
      .build(),
    columnGroup: 'document',
    order: 1,
    width: 250,
    formatter: (e: FormatterEvent) => (
      <KardexMovementTypeCell value={e.value} reference={formatReference(rowOf(e))} />
    ),
  },
  {
    ...textField().name('sourceId').label('Referencia').build(),
    order: 2,
    width: 150,
    visible: false,
    visibleOnForm: false,
    formatter: (e: FormatterEvent) => formatReference(e.data as StockMovementRecord),
  },
  {
    ...entityField(API + 'warehouses', 'id', 'name')
      .name('warehouse')
      .label('Almacén')
      .build(),
    order: 3,
    width: 150,
    visible: !scoped,
    formatter: (e: FormatterEvent) => {
      const value = e.value;
      if (value && typeof value === 'object')
        return String((value as { name?: string }).name ?? '');
      return String(value ?? '');
    },
  },
  {
    ...entityField(API + 'products', 'id', 'name')
      .name('product')
      .label('Producto')
      .build(),
    order: 4,
    width: 220,
    visible: !scoped,
    formatter: (e: FormatterEvent) => {
      const value = e.value;
      if (value && typeof value === 'object') {
        const p = value as { code?: string | null; name?: string };
        return [p.code, p.name].filter(Boolean).join(' - ');
      }
      return String(value ?? '');
    },
  },
  {
    ...textField().name('qtyIn').label('Cant.').build(),
    columnGroup: 'entries',
    order: 5,
    width: 80,
    align: 'right',
    filterable: false,
    formatter: (e: FormatterEvent) => formatQty(e.value),
  },
  {
    ...textField().name('unitCost').label('Costo unit.').build(),
    columnGroup: 'entries',
    order: 6,
    width: 95,
    align: 'right',
    filterable: false,
    visible: showValuation,
    formatter: formatUnitCost,
  },
  {
    ...textField().name('lineValue').label('Total').build(),
    columnGroup: 'entries',
    order: 7,
    width: 95,
    align: 'right',
    filterable: false,
    visible: showValuation,
    formatter: formatLineValueForIn,
  },
  {
    ...textField().name('qtyOut').label('Cant.').build(),
    columnGroup: 'exits',
    order: 8,
    width: 80,
    align: 'right',
    filterable: false,
    formatter: (e: FormatterEvent) => formatQty(e.value),
  },
  {
    ...textField().name('_unitCostExit').label('Costo unit.').build(),
    columnGroup: 'exits',
    order: 9,
    width: 95,
    align: 'right',
    filterable: false,
    visible: showValuation,
    formatter: (e: FormatterEvent) => (rowOf(e)?.qtyOut ? formatCurrency(rowOf(e)?.unitCost) : '—'),
  },
  {
    ...textField().name('_lineValueExit').label('Total').build(),
    columnGroup: 'exits',
    order: 10,
    width: 95,
    align: 'right',
    filterable: false,
    visible: showValuation,
    formatter: formatLineValueForOut,
  },
  {
    ...textField().name('runningBalance').label('Stock').build(),
    columnGroup: 'balance',
    order: 11,
    width: 85,
    align: 'right',
    filterable: false,
    formatter: (e: FormatterEvent) => formatQty(e.value),
  },
  {
    ...textField().name('runningValue').label('Valor').build(),
    columnGroup: 'balance',
    order: 12,
    width: 105,
    align: 'right',
    filterable: false,
    visible: showValuation,
    formatter: (e: FormatterEvent) => formatCurrency(e.value),
  },
  {
    ...textField().name('note').label('Nota').build(),
    order: 13,
    width: 220,
    formatter: (e: FormatterEvent) => formatNote(e.value),
  },
];

function buildGridUrl(
  productId: string | null,
  warehouseId: string | null,
  from: string | null,
  to: string | null,
  valuation: KardexValuationMethod,
): string {
  const params = new URLSearchParams({ valuation });
  if (productId) params.set('product', `${API}products/${productId}`);
  if (warehouseId) params.set('warehouse', `${API}warehouses/${warehouseId}`);
  if (from) params.set('from', from);
  if (to) params.set('to', to);
  return `${BASE_URL}?${params.toString()}`;
}

export const KardexPage = () => {
  const { t } = useCoreTranslation();
  const location = useLocation();
  const [searchParams, setSearchParams] = useSearchParams();
  const { currentBranchId } = useSessionInfo();
  const { warehouses } = useWarehouses({ branchId: currentBranchId });
  const inInventoryHub = location.pathname.startsWith('/inventory');

  const productId = searchParams.get('product');
  const warehouseId = searchParams.get('warehouse');
  const from = searchParams.get('from');
  const to = searchParams.get('to');
  const valuation = (searchParams.get('valuation') as KardexValuationMethod | null) ?? 'pp';

  const updateSearchParams = useCallback(
    (patch: Record<string, string | null | undefined>) => {
      const next = new URLSearchParams(searchParams);
      for (const [key, value] of Object.entries(patch)) {
        if (value) next.set(key, value);
        else next.delete(key);
      }
      setSearchParams(next, { replace: true });
    },
    [searchParams, setSearchParams],
  );

  const urlFilters = useMemo(() => {
    const rules: Array<{ field: string; operator: string; value: string }> = [];
    if (warehouseId) {
      rules.push({ field: 'warehouse', operator: '=', value: `${API}warehouses/${warehouseId}` });
    }
    if (productId) {
      rules.push({ field: 'product', operator: '=', value: `${API}products/${productId}` });
    }
    return rules;
  }, [productId, warehouseId]);

  const warehouseOptions = useMemo(
    () => warehouses.map((warehouse) => ({ value: warehouse.id, label: warehouse.name })),
    [warehouses],
  );

  const gridUrl = useMemo(
    () => buildGridUrl(productId, warehouseId, from, to, valuation),
    [productId, warehouseId, from, to, valuation],
  );
  const showValuation = true;
  const gridFields = useMemo(
    () => buildFields(Boolean(productId), showValuation),
    [productId, showValuation],
  );
  const columnChooserState = useColumnChooser(KARDEX_PRESET_RESOURCE, gridFields);
  const gridKey = `${productId ?? 'all'}-${warehouseId ?? 'all'}-${from ?? ''}-${to ?? ''}-${valuation}-${columnChooserState.visibleColumns.join(',')}`;

  const kardexToolbar = useMemo(
    () => ({
      utility: [
        {
          key: 'kardex-pdf',
          text: 'PDF',
          icon: 'ph-file-pdf',
          disabled: !productId,
          onClick: () => {
            if (!productId) return;
            openKardexExport('pdf', { productId, warehouseId, from, to, valuation });
          },
        },
        {
          key: 'kardex-xlsx',
          text: 'Excel',
          icon: 'ph-file-xls',
          disabled: !productId,
          onClick: () => {
            if (!productId) return;
            openKardexExport('xlsx', { productId, warehouseId, from, to, valuation });
          },
        },
      ],
    }),
    [productId, warehouseId, from, to, valuation],
  );

  const renderColumnChooser = useCallback(
    () => (
      <ColumnChooserButton
        state={columnChooserState}
        triggerLabel={t('crudPage.columnChooserLabel')}
        selectAllLabel={t('crudPage.columnChooserSelectAll')}
        resetLabel={t('crudPage.columnChooserReset')}
      />
    ),
    [columnChooserState, t],
  );

  const kardexDashboard = (
    <KardexDashboard
      productId={productId}
      warehouseId={warehouseId}
      from={from}
      to={to}
      valuation={valuation}
      warehouseOptions={warehouseOptions}
      onProductChange={(value) => updateSearchParams({ product: value })}
      onWarehouseChange={(value) => updateSearchParams({ warehouse: value })}
      onFromChange={(value) => updateSearchParams({ from: value })}
      onToChange={(value) => updateSearchParams({ to: value })}
      onValuationChange={(value) => updateSearchParams({ valuation: value })}
    />
  );

  return (
    <div
      className={
        inInventoryHub ? 'inventory-kardex-page kardex-page' : 'view-wrapper-scroll kardex-page'
      }
      data-testid={inInventoryHub ? 'inventory-kardex-page' : undefined}
    >
      <DataGridView
        key={gridKey}
        id="kardex"
        appearance="page"
        title={inInventoryHub ? '' : 'Kardex'}
        mode={inInventoryHub ? 'normal' : undefined}
        url={gridUrl}
        fields={gridFields}
        filter={urlFilters}
        columnGroupDefs={KARDEX_COLUMN_GROUP_DEFS}
        toolbar={kardexToolbar}
        sort={[{ selector: 'occurredAt', desc: false }]}
        allowAdd={false}
        allowEdit={false}
        allowDelete={false}
        manualLoad={!productId}
        filterRow={false}
        visibleColumns={columnChooserState.visibleColumns}
        beforeToolbar={renderColumnChooser}
        aboveGrid={kardexDashboard}
        emptyState={
          productId
            ? {
                title: 'Sin movimientos',
                description:
                  'No hay movimientos registrados para este producto en el período visible.',
                icon: 'list-magnifying-glass',
              }
            : {
                title: 'Selecciona un producto',
                description: 'Elige un producto en el panel superior para cargar el kardex.',
                icon: 'package',
              }
        }
      />
      {productId && (
        <p className="kardex-note" data-testid="kardex-note">
          <i className="ph ph-shield-check" aria-hidden="true" />
          Los movimientos no se editan: para corregir uno, registra un ajuste y queda en el registro
          de actividad.
        </p>
      )}
    </div>
  );
};
