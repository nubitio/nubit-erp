import { useState, useCallback, useEffect, useMemo } from 'react';
import { useSearchParams } from 'react-router-dom';
import DateBox from 'devextreme-react/date-box';
import SelectBox, { type SelectBoxTypes } from 'devextreme-react/select-box';
import { Button } from 'devextreme-react';
import { Constants } from '@core/common/Constants';
import { DateUtils } from '@shared/lib/DateUtils';
import { defineFields } from '@core/crud/fieldContract';
import { defineResource, SmartCrudPage } from '@core/crud';
import { httpClient } from '@shared/api/HttpClient';
import { lastValueFrom } from 'rxjs';

// ─── Types ───────────────────────────────────────────────────────────────────

interface StockMovementRecord extends Record<string, unknown> {
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

// ─── Helpers ─────────────────────────────────────────────────────────────────

const BASE_URL = Constants.API_BASE_URL + 'stock-movements';

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

const formatDate = (value: unknown): string => {
  if (!value) return '';
  try {
    return new Date(value as string).toLocaleString('es-PE', {
      day: '2-digit', month: '2-digit', year: 'numeric',
      hour: '2-digit', minute: '2-digit',
    });
  } catch {
    return String(value);
  }
};

// ─── Field contract factory ────────────────────────────────────────────────

const buildFieldContract = (showProductColumns: boolean, showBalance: boolean) =>
  defineFields<StockMovementRecord>({
    source: 'hydra',
    strategy: 'augment',
    directives: [
      { kind: 'override', key: 'id',          patch: { visible: false, visibleOnForm: false } },
      { kind: 'override', key: 'movementType', patch: { visible: false, visibleOnForm: false } },
      { kind: 'override', key: 'sourceType',   patch: { visible: false, visibleOnForm: false } },
      {
        kind: 'override', key: 'occurredAt',
        patch: { order: 0, width: 150, label: 'Fecha', formatter: (e) => formatDate(e.value) },
      },
      {
        kind: 'override', key: 'warehouse',
        patch: {
          order: 1, width: 160, label: 'Almacén',
          visible: showProductColumns, visibleOnForm: false,
          formatter: (e) => formatWarehouse(e.value),
        },
      },
      {
        kind: 'override', key: 'product',
        patch: {
          order: 2, width: 220, label: 'Producto',
          visible: showProductColumns, visibleOnForm: false,
          formatter: (e) => formatProduct(e.value),
        },
      },
      {
        kind: 'override', key: 'movementTypeLabel',
        patch: { order: 3, width: 160, label: 'Tipo' },
      },
      {
        kind: 'override', key: 'sourceId',
        patch: { order: 4, width: 180, label: 'Referencia' },
      },
      {
        kind: 'override', key: 'qtyIn',
        patch: { order: 5, width: 100, label: 'Entrada', align: 'right', formatter: (e) => formatQty(e.value) },
      },
      {
        kind: 'override', key: 'qtyOut',
        patch: { order: 6, width: 100, label: 'Salida', align: 'right', formatter: (e) => formatQty(e.value) },
      },
      {
        kind: 'override', key: 'runningBalance',
        patch: {
          order: 7, width: 110, label: 'Saldo',
          align: 'right',
          visible: showBalance,
          visibleOnForm: false,
          formatter: (e) => formatQty(e.value),
        },
      },
      {
        kind: 'override', key: 'note',
        patch: { order: 8, width: 220, label: 'Nota' },
      },
    ],
  });

// ─── Page ─────────────────────────────────────────────────────────────────────

type SimpleOption = { id: string; name: string };

/** Build the API URL from current filter state. `null` values → param omitted. */
const buildUrl = (
  warehouseId: string | null,
  productId: string | null,
  fromDate: Date | null,
  toDate: Date | null,
): string => {
  const params = new URLSearchParams();
  if (warehouseId) params.set('warehouse', warehouseId);
  if (productId)   params.set('product',   productId);
  if (fromDate)    params.set('from',       DateUtils.dateFormatter(fromDate));
  if (toDate)      params.set('to',         DateUtils.dateFormatter(toDate));
  return `${BASE_URL}?${params.toString()}`;
};

export const KardexPage = () => {
  const [searchParams] = useSearchParams();

  // Pre-fill from URL params (e.g. navigation from StockItemPage / Dashboard)
  const initialWarehouse = searchParams.get('warehouse');
  const initialProduct   = searchParams.get('product');

  const [warehouseId, setWarehouseId] = useState<string | null>(initialWarehouse);
  const [productId,   setProductId]   = useState<string | null>(initialProduct);
  const [fromDate,    setFromDate]     = useState<Date | null>(null);
  const [toDate,      setToDate]       = useState<Date | null>(null);

  // activeUrl drives the grid; initialised on mount → shows filtered (or all) movements immediately
  const [activeUrl, setActiveUrl] = useState<string>(() =>
    buildUrl(initialWarehouse, initialProduct, null, null),
  );

  const [activeProductId, setActiveProductId] = useState<string | null>(initialProduct);

  const [warehouses, setWarehouses] = useState<SimpleOption[]>([]);
  const [products,   setProducts]   = useState<SimpleOption[]>([]);

  useEffect(() => {
    lastValueFrom(httpClient.get<any>(`${Constants.API_BASE_URL}warehouses?itemsPerPage=200`))
      .then((r) =>
        setWarehouses(
          (r.data?.['hydra:member'] ?? []).map((w: any) => ({ id: w.id, name: w.name })),
        ),
      )
      .catch(() => {});
  }, []);

  useEffect(() => {
    lastValueFrom(httpClient.get<any>(`${Constants.API_BASE_URL}products?itemsPerPage=500`))
      .then((r) =>
        setProducts(
          (r.data?.['hydra:member'] ?? []).map((p: any) => ({
            id: p.id,
            name: [p.code, p.name].filter(Boolean).join(' - '),
          })),
        ),
      )
      .catch(() => {});
  }, []);

  const handleSearch = useCallback(
    () => setActiveUrl(buildUrl(warehouseId, productId, fromDate, toDate)),
    [warehouseId, productId, fromDate, toDate],
  );

  const handleClear = useCallback(() => {
    setWarehouseId(null);
    setProductId(null);
    setFromDate(null);
    setToDate(null);
    setActiveUrl(buildUrl(null, null, null, null));
  }, []);

  // Columns adapt to active filters:
  //   • product/warehouse columns hidden when filtering by a specific product (redundant)
  //   • runningBalance only meaningful when filtered by product (cross-product sum has no sense)
  const showProductColumns = activeProductId === null;
  const showBalance        = activeProductId !== null;

  const fieldContract = useMemo(
    () => buildFieldContract(showProductColumns, showBalance),
    [showProductColumns, showBalance],
  );

  const resource = useMemo(
    () =>
      defineResource<StockMovementRecord>({
        id: `kardex-${activeUrl}`,          // key change forces grid remount on filter
        title: 'Kardex — Movimientos de stock',
        apiUrl: activeUrl,
        fields: [],
        fieldContract,
        sort: [{ selector: 'occurredAt', desc: true }],
        permissions: { canAdd: false, canEdit: false, canDelete: false },
      }),
    [activeUrl, fieldContract],
  );

  const applySearch = useCallback(() => {
    setActiveProductId(productId);          // track which product is active for column logic
    setActiveUrl(buildUrl(warehouseId, productId, fromDate, toDate));
  }, [warehouseId, productId, fromDate, toDate]);

  return (
    <div id="wrapper">
      {/* ── Filtros ── */}
      <div
        style={{
          display: 'flex',
          gap: 12,
          flexWrap: 'wrap',
          padding: '12px 0 8px',
          alignItems: 'flex-end',
        }}
      >
        <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
          <label style={{ fontSize: 12, color: '#666' }}>Almacén</label>
          <SelectBox
            dataSource={warehouses}
            placeholder="Todos"
            displayExpr="name"
            valueExpr="id"
            value={warehouseId}
            width={220}
            stylingMode="outlined"
            showClearButton
            searchEnabled
            onValueChanged={(e: SelectBoxTypes.ValueChangedEvent) =>
              setWarehouseId(e.value ?? null)
            }
          />
        </div>

        <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
          <label style={{ fontSize: 12, color: '#666' }}>Producto</label>
          <SelectBox
            dataSource={products}
            placeholder="Todos"
            displayExpr="name"
            valueExpr="id"
            value={productId}
            width={280}
            stylingMode="outlined"
            showClearButton
            searchEnabled
            onValueChanged={(e: SelectBoxTypes.ValueChangedEvent) =>
              setProductId(e.value ?? null)
            }
          />
        </div>

        <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
          <label style={{ fontSize: 12, color: '#666' }}>Desde</label>
          <DateBox
            value={fromDate}
            width={140}
            stylingMode="outlined"
            displayFormat="dd/MM/yyyy"
            showClearButton
            onValueChanged={(e) => setFromDate(e.value ?? null)}
          />
        </div>

        <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
          <label style={{ fontSize: 12, color: '#666' }}>Hasta</label>
          <DateBox
            value={toDate}
            width={140}
            stylingMode="outlined"
            displayFormat="dd/MM/yyyy"
            showClearButton
            onValueChanged={(e) => setToDate(e.value ?? null)}
          />
        </div>

        <Button
          text="Filtrar"
          type="default"
          stylingMode="contained"
          icon="search"
          onClick={applySearch}
        />
        <Button
          text="Limpiar"
          type="normal"
          stylingMode="outlined"
          icon="clear"
          onClick={handleClear}
        />
      </div>

      {/* ── Grilla ── */}
      <SmartCrudPage resource={resource} />
    </div>
  );
};
