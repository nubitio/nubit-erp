/* eslint-disable react-hooks/refs -- grid actions use a stable page ref during render */
import { useCallback, useMemo, useRef, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { useQueryClient } from '@tanstack/react-query';
import {
  FieldType,
  SchemaCrudPage,
  defineFields,
  defineResource,
  type BulkAction,
  type DataRecord,
  type Field,
  type GridCellContext,
} from '@nubitio/crud';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { HubPanel } from '@nubitio/ui';
import toast from '@shared/lib/SharedUtils';
import { useTenantRuntimeConfig } from '@shared/hooks';
import { useSessionInfo } from '@shared/sessionInfo';
import { useWarehouses } from '@shared/hooks/useWarehouses';
import { InventorySummaryCards } from '@features/inventory/InventorySummaryCards';
import { InventoryWarehouseTabs } from '@features/inventory/InventoryWarehouseTabs';
import {
  InventoryFilterChips,
  type StockStatusFilter,
} from '@features/inventory/InventoryFilterChips';
import { StockAdjustDrawer, type StockAdjustTarget } from '@features/inventory/StockAdjustDrawer';
import {
  BulkStockReceiptDialog,
  parseStockItemCompositeId,
  type BulkStockReceiptTarget,
} from '@features/inventory/BulkStockReceiptDialog';
import { useInventoryCategories } from '@features/inventory/useInventoryCategories';
import { resolveStockUnitLabel } from '@features/inventory/inventoryUnitLabel';
import { stockCoverageColor, stockCoveragePercent } from '@features/inventory/stockCoverage';
import { extractEntityId } from '@features/inventory/inventoryEntityIri';
import { productIriFromValue, warehouseIriFromValue } from '@features/inventory/stockEntryApi';
import '@features/inventory/InventoryHub.scss';

const API = getCoreApiBaseUrl();

interface StockItemRecord extends DataRecord {
  id: string;
  warehouse: { id?: string; name?: string; code?: string | null } | string;
  product: {
    id?: string;
    code?: string | null;
    name?: string;
    category?: { id?: string; name?: string } | null;
    unitOfMeasure?: { id?: number; name?: string } | null;
  } | string;
  qtyOnHand: string;
  qtyReserved: string;
  qtyAvailable: string;
  minStock?: string | null;
  unitName?: string | null;
  unitCode?: string | null;
  updatedAt: string;
}

const formatProductLabel = (value: unknown) => {
  if (value && typeof value === 'object') {
    const product = value as { code?: string | null; name?: string };
    return [product.code, product.name].filter(Boolean).join(' - ');
  }
  return String(value ?? '');
};

const formatProductWithCategory = (value: unknown) => {
  if (!value || typeof value !== 'object') return String(value ?? '');
  const product = value as {
    code?: string | null;
    name?: string;
    category?: { name?: string } | null;
  };
  const label = [product.code, product.name].filter(Boolean).join(' - ');
  const category = product.category?.name;
  if (!category) return label;
  return (
    <span className="stock-product-link__content">
      <span className="stock-product-link__name">{label}</span>
      <span className="stock-product-link__category">{category}</span>
    </span>
  );
};

const formatWarehouse = (value: unknown) => {
  if (value && typeof value === 'object') {
    return String((value as { name?: string }).name ?? '');
  }
  return String(value ?? '');
};

const formatQty = (value: unknown) => {
  const numeric = Number(value ?? 0);
  return Number.isFinite(numeric)
    ? numeric.toLocaleString('es-PE', { maximumFractionDigits: 3, minimumFractionDigits: 0 })
    : '';
};

function formatQtyWithCoverage({ value, data }: GridCellContext) {
  const qtyLabel = formatQty(value);
  const pct = stockCoveragePercent(String(value ?? '0'), data?.minStock as string | null | undefined);
  const color = stockCoverageColor(pct);

  return (
    <span style={{ display: 'flex', flexDirection: 'column', gap: 6, alignItems: 'flex-end' }}>
      <span>{qtyLabel}</span>
      <span className="stock-coverage-cell">
        <span className="stock-coverage-cell__track">
          <span
            className="stock-coverage-cell__fill"
            style={{ width: `${pct}%`, background: color }}
          />
        </span>
        <span className="stock-coverage-cell__pct" style={{ color }}>{pct}%</span>
      </span>
    </span>
  );
}

function makeProductFormatter() {
  return function ProductCell({ value }: GridCellContext) {
    return <span className="stock-product-cell">{formatProductWithCategory(value)}</span>;
  };
}

function makeActionsFormatter(
  onAdjust: (row: StockItemRecord) => void,
  onKardex: (row: StockItemRecord) => void,
) {
  return function ActionsCell({ data }: GridCellContext) {
    const row = data as StockItemRecord | undefined;
    if (!row) return null;

    const goKardex = (e: React.MouseEvent) => {
      e.stopPropagation();
      onKardex(row);
    };

    const goAdjust = (e: React.MouseEvent) => {
      e.stopPropagation();
      onAdjust(row);
    };

    return (
      <span style={{ display: 'inline-flex', gap: 6 }}>
        <button title="Ver Kardex" onClick={goKardex} style={actionBtnStyle}>
          <i className="ph ph-list-magnifying-glass" />
        </button>
        <button
          title="Ajustar stock"
          onClick={goAdjust}
          style={actionBtnStyle}
          data-testid="stock-adjust-button"
        >
          <i className="ph ph-pencil-simple" />
        </button>
      </span>
    );
  };
}

const actionBtnStyle: React.CSSProperties = {
  background: 'none',
  border: '1px solid var(--base-border-color, #d1d5db)',
  borderRadius: 4,
  cursor: 'pointer',
  padding: '2px 6px',
  fontSize: 14,
  color: 'var(--accent-color, #3b82f6)',
  lineHeight: 1,
};

function rowToAdjustTarget(row: StockItemRecord): StockAdjustTarget {
  return {
    productId: extractEntityId(row.product),
    productLabel: formatProductLabel(row.product),
    productIri: productIriFromValue(row.product),
    unitLabel: resolveStockUnitLabel({
      unitName: row.unitName,
      unitCode: row.unitCode,
      product: row.product,
    }),
    warehouseId: extractEntityId(row.warehouse),
    warehouseLabel: formatWarehouse(row.warehouse),
    warehouseIri: warehouseIriFromValue(row.warehouse),
    qtyOnHand: String(row.qtyOnHand ?? '0'),
  };
}

function buildStockItemsUrl(
  categoryFilterId: string | null,
  statusFilter: StockStatusFilter,
): string {
  const params = new URLSearchParams();
  if (categoryFilterId) params.set('categoryId', categoryFilterId);
  if (statusFilter !== 'all') params.set('stockStatus', statusFilter);
  const query = params.toString();
  return `${API}stock-items${query ? `?${query}` : ''}`;
}

export const StockItemPage = () => {
  const navigate = useNavigate();
  const location = useLocation();
  const queryClient = useQueryClient();
  const { currentBranchId } = useSessionInfo();
  const { data: runtimeConfig } = useTenantRuntimeConfig();
  const { warehouses } = useWarehouses({ branchId: currentBranchId });
  const { data: categories = [] } = useInventoryCategories();

  const inInventoryHub = location.pathname.startsWith('/inventory');
  const showWarehouseTabs = runtimeConfig?.ui.inventory.showWarehouseSelector ?? false;
  const showWarehouseColumn = !runtimeConfig?.profile.simpleStoreMode;

  const [warehouseFilterId, setWarehouseFilterId] = useState<string | null>(null);
  const [categoryFilterId, setCategoryFilterId] = useState<string | null>(null);
  const [statusFilter, setStatusFilter] = useState<StockStatusFilter>('all');
  const [adjustTarget, setAdjustTarget] = useState<StockAdjustTarget | null>(null);
  const [bulkTargets, setBulkTargets] = useState<BulkStockReceiptTarget[]>([]);
  const [bulkOpen, setBulkOpen] = useState(false);
  const [gridKey, setGridKey] = useState(0);
  const selectedRowsRef = useRef<Map<string, StockItemRecord>>(new Map());

  const refreshInventoryViews = useCallback(() => {
    setGridKey((key) => key + 1);
    void queryClient.invalidateQueries({ queryKey: ['inventory-summary'] });
  }, [queryClient]);

  const openKardex = useCallback((row: StockItemRecord) => {
    const params = new URLSearchParams();
    const whId = extractEntityId(row.warehouse);
    const prId = extractEntityId(row.product);
    if (whId) params.set('warehouse', whId);
    if (prId) params.set('product', prId);
    const kardexPath = inInventoryHub ? '/inventory/movements' : '/kardex';
    navigate(`${kardexPath}?${params.toString()}`);
  }, [inInventoryHub, navigate]);

  const openAdjustDrawer = useCallback((row: StockItemRecord) => {
    setAdjustTarget(rowToAdjustTarget(row));
  }, []);

  const bulkActions = useMemo<BulkAction[]>(
    () => [
      {
        key: 'bulk-receipt',
        label: 'Ingreso masivo',
        icon: 'ph-arrow-line-down',
        confirmMessage: '¿Registrar el mismo ingreso para todos los productos seleccionados?',
        onAction: (selectedIds) => {
          const targets = selectedIds.flatMap((id) => {
            const parsed = parseStockItemCompositeId(String(id));
            if (!parsed) return [];
            const row = selectedRowsRef.current.get(String(id));
            return [{
              stockItemId: String(id),
              productId: parsed.productId,
              warehouseId: parsed.warehouseId,
              productLabel: row ? formatProductLabel(row.product) : String(id),
            } satisfies BulkStockReceiptTarget];
          });
          if (targets.length === 0) return;
          setBulkTargets(targets);
          setBulkOpen(true);
        },
      },
      {
        key: 'cycle-count',
        label: 'Conteo cíclico',
        icon: 'ph-clipboard-text',
        onAction: (selectedIds) => {
          const parsedRows = selectedIds.flatMap((id) => {
            const parsed = parseStockItemCompositeId(String(id));
            return parsed ? [parsed] : [];
          });
          if (parsedRows.length === 0) return;

          const warehouseIds = [...new Set(parsedRows.map((row) => row.warehouseId))];
          if (warehouseIds.length !== 1) {
            toast('Selecciona productos del mismo almacén para el conteo cíclico', 'warning');
            return;
          }

          const params = new URLSearchParams();
          params.set('warehouse', warehouseIds[0]);
          params.set('products', [...new Set(parsedRows.map((row) => row.productId))].join(','));
          params.set('returnTo', location.pathname);
          navigate(`/inventory/cycle-count?${params.toString()}`);
        },
      },
    ],
    [location.pathname, navigate],
  );

  const columnPresets = useMemo(() => {
    const compactColumns = ['product', 'qtyOnHand', '_actions'];
    const basicColumns = showWarehouseColumn
      ? ['product', 'qtyOnHand', 'qtyAvailable', 'minStock', '_actions']
      : ['product', 'qtyOnHand', 'qtyAvailable', 'minStock', '_actions'];
    const fullColumns = showWarehouseColumn
      ? ['warehouse', 'product', 'qtyOnHand', 'qtyReserved', 'qtyAvailable', 'minStock', 'updatedAt', '_actions']
      : ['product', 'qtyOnHand', 'qtyReserved', 'qtyAvailable', 'minStock', 'updatedAt', '_actions'];

    return [
      { key: 'compact', label: 'Compacto', columns: compactColumns },
      { key: 'basic', label: 'Básico', columns: basicColumns },
      { key: 'full', label: 'Completo', columns: fullColumns },
    ];
  }, [showWarehouseColumn]);

  const warehouseFilter = useMemo(() => {
    if (!warehouseFilterId) return [];
    return [{ field: 'warehouse', operator: '=', value: `${API}warehouses/${warehouseFilterId}` }];
  }, [warehouseFilterId]);

  const resource = useMemo(
    () =>
      defineResource<StockItemRecord>(
        buildStockItemsUrl(categoryFilterId, statusFilter),
        {
        id: `stock-item-${warehouseFilterId ?? 'all'}-${categoryFilterId ?? 'all'}-${statusFilter}`,
        title: inInventoryHub ? '' : 'Existencias',
        mode: 'normal',
        filter: warehouseFilter,
        bulkActions,
        fieldContract: defineFields<StockItemRecord>({
          source: 'hydra',
          strategy: 'augment',
          directives: [
            { kind: 'override', key: 'id', patch: { visible: false, visibleOnForm: false } },
            { kind: 'override', key: 'unitName', patch: { visible: false, visibleOnForm: false } },
            { kind: 'override', key: 'unitCode', patch: { visible: false, visibleOnForm: false } },
            {
              kind: 'override',
              key: 'warehouse',
              patch: {
                order: 0,
                width: 160,
                label: 'Almacén',
                visible: showWarehouseColumn,
                visibleOnForm: showWarehouseColumn,
                valueField: '_iri',
                textField: 'name',
                url: API + 'warehouses',
                selectedFilterOperation: '=',
                formatter: (e) => formatWarehouse(e.value),
              },
            },
            {
              kind: 'override',
              key: 'product',
              patch: {
                order: 1,
                width: 300,
                label: 'Producto',
                valueField: '_iri',
                textField: 'name',
                url: API + 'products',
                selectedFilterOperation: '=',
                formatter: makeProductFormatter(),
              },
            },
            {
              kind: 'override',
              key: 'qtyOnHand',
              patch: {
                order: 2,
                width: 150,
                label: 'En mano',
                align: 'right',
                formatter: formatQtyWithCoverage,
              },
            },
            {
              kind: 'override',
              key: 'qtyReserved',
              patch: {
                order: 3,
                width: 110,
                label: 'Reservado',
                align: 'right',
                formatter: (e) => formatQty(e.value),
              },
            },
            {
              kind: 'override',
              key: 'qtyAvailable',
              patch: {
                order: 4,
                width: 110,
                label: 'Disponible',
                align: 'right',
                formatter: (e) => formatQty(e.value),
              },
            },
            {
              kind: 'override',
              key: 'minStock',
              patch: {
                order: 5,
                width: 100,
                label: 'Mínimo',
                align: 'right',
                formatter: (e) => formatQty(e.value),
              },
            },
            { kind: 'override', key: 'updatedAt', patch: { order: 6, width: 150, label: 'Actualizado' } },
            {
              kind: 'append' as const,
              field: {
                name: '_actions',
                type: FieldType.TEXT,
                label: '',
                order: 7,
                width: 80,
                visible: true,
                visibleOnForm: false,
                sortable: false,
                filterable: false,
                formatter: makeActionsFormatter(openAdjustDrawer, openKardex),
              } as Field,
            },
          ],
        }),
        sort: [{ selector: 'updatedAt', desc: true }],
        defaultPreset: 'basic',
        columnPresets,
        emptyState: {
          title: 'Sin existencias',
          description: 'No hay stock registrado en ningún almacén.',
          icon: 'package',
        },
        permissions: {
          canAdd: false,
          canEdit: false,
          canDelete: false,
        },
      }),
    [
      bulkActions,
      categoryFilterId,
      columnPresets,
      inInventoryHub,
      openAdjustDrawer,
      openKardex,
      showWarehouseColumn,
      statusFilter,
      warehouseFilter,
      warehouseFilterId,
    ],
  );

  const stockDashboard = useMemo(() => {
    if (!inInventoryHub) return undefined;

    return (
      <HubPanel variant="flush">
        <InventorySummaryCards warehouseId={warehouseFilterId} />
        {showWarehouseTabs && (
          <InventoryWarehouseTabs
            warehouses={warehouses}
            selectedWarehouseId={warehouseFilterId}
            onChange={setWarehouseFilterId}
          />
        )}
        <InventoryFilterChips
          categories={categories}
          selectedCategoryId={categoryFilterId}
          onCategoryChange={setCategoryFilterId}
          selectedStatus={statusFilter}
          onStatusChange={setStatusFilter}
        />
      </HubPanel>
    );
  }, [
    categories,
    categoryFilterId,
    inInventoryHub,
    showWarehouseTabs,
    statusFilter,
    warehouseFilterId,
    warehouses,
  ]);

  return (
    <div className="inventory-stock-page" data-testid="inventory-stock-page">
      <SchemaCrudPage
        key={gridKey}
        resource={resource}
        aboveGrid={stockDashboard}
        onSelectionChanged={(rows) => {
          const map = new Map<string, StockItemRecord>();
          for (const row of rows as StockItemRecord[]) {
            map.set(String(row.id), row);
          }
          selectedRowsRef.current = map;
        }}
      />

      <StockAdjustDrawer
        target={adjustTarget}
        onClose={() => setAdjustTarget(null)}
        onApplied={refreshInventoryViews}
      />

      <BulkStockReceiptDialog
        targets={bulkTargets}
        open={bulkOpen}
        onClose={() => setBulkOpen(false)}
        onApplied={refreshInventoryViews}
      />
    </div>
  );
};
