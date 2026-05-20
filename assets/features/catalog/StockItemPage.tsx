import { useNavigate } from 'react-router-dom';
import {
  FieldType,
  SmartCrudPage,
  defineFields,
  defineResource,
  type DataRecord,
  type GridCellContext,
} from '@nubit/core';
import { API_BASE_URL } from '@app/constants';

// ─── Types ───────────────────────────────────────────────────────────────────

interface StockItemRecord extends DataRecord {
  id: string;
  warehouse: { id?: string; name?: string } | string;
  product: { id?: string; code?: string | null; name?: string } | string;
  qtyOnHand: string;
  qtyReserved: string;
  qtyAvailable: string;
  updatedAt: string;
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

const formatProduct = (value: unknown) => {
  if (value && typeof value === 'object') {
    const product = value as { code?: string | null; name?: string };
    return [product.code, product.name].filter(Boolean).join(' - ');
  }
  return String(value ?? '');
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

/** Extracts the raw UUID/slug from an IRI string like "/api/warehouses/abc-123" */
const iriToId = (iri: string) => iri.split('/').pop() ?? iri;

// ─── Row-action buttons ───────────────────────────────────────────────────────

/**
 * Factory that returns a formatter which captures `navigate` via closure.
 * Renders two icon-buttons per row:
 *   📋 Kardex  → /kardex?warehouse=X&product=Y  (pre-fills both filters)
 *   ✏  Ajustar → /stock-adjustment              (opens adjustment page)
 */
function makeActionsFormatter(navigate: ReturnType<typeof useNavigate>) {
  return function ActionsCell({ data }: GridCellContext) {
    const wh = data?.warehouse;
    const pr = data?.product;

    const whId = wh && typeof wh === 'object' ? (wh as { id?: string }).id : String(wh ?? '');
    const prId = pr && typeof pr === 'object' ? (pr as { id?: string }).id : String(pr ?? '');

    const goKardex = (e: React.MouseEvent) => {
      e.stopPropagation();
      const params = new URLSearchParams();
      if (whId) params.set('warehouse', iriToId(whId));
      if (prId) params.set('product',   iriToId(prId));
      navigate(`/kardex?${params.toString()}`);
    };

    const goAdjust = (e: React.MouseEvent) => {
      e.stopPropagation();
      navigate('/stock-adjustment');
    };

    return (
      <span style={{ display: 'inline-flex', gap: 6 }}>
        <button
          title="Ver Kardex"
          onClick={goKardex}
          style={actionBtnStyle}
        >
          <i className="ph ph-list-magnifying-glass" />
        </button>
        <button
          title="Ajustar stock"
          onClick={goAdjust}
          style={actionBtnStyle}
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

// ─── Page ─────────────────────────────────────────────────────────────────────

export const StockItemPage = () => {
  const navigate = useNavigate();

  const resource = defineResource<StockItemRecord>({
    id: 'stock-item',
    title: 'Existencias',
    apiUrl: API_BASE_URL + 'stock-items',
    fields: [],
    fieldContract: defineFields<StockItemRecord>({
      source: 'hydra',
      strategy: 'augment',
      directives: [
        { kind: 'override', key: 'id', patch: { visible: false, visibleOnForm: false } },
        {
          kind: 'override',
          key: 'warehouse',
          patch: {
            order: 0,
            width: 180,
            label: 'Almacén',
            valueField: '_iri',
            textField: 'name',
            url: API_BASE_URL + 'warehouses',
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
            url: API_BASE_URL + 'products',
            selectedFilterOperation: '=',
            formatter: (e) => formatProduct(e.value),
          },
        },
        {
          kind: 'override',
          key: 'qtyOnHand',
          patch: { order: 2, width: 130, label: 'Stock actual', align: 'right', formatter: (e) => formatQty(e.value) },
        },
        {
          kind: 'override',
          key: 'qtyReserved',
          patch: { order: 3, width: 120, label: 'Reservado', align: 'right', formatter: (e) => formatQty(e.value) },
        },
        {
          kind: 'override',
          key: 'qtyAvailable',
          patch: { order: 4, width: 120, label: 'Disponible', align: 'right', formatter: (e) => formatQty(e.value) },
        },
        { kind: 'override', key: 'updatedAt', patch: { order: 5, width: 160, label: 'Actualizado' } },
        {
          kind: 'append' as const,
          field: {
            name: '_actions',
            type: FieldType.TEXT,
            label: '',
            order: 6,
            width: 80,
            formatter: makeActionsFormatter(navigate),
          } as any,
        },
      ],
    }),
    sort: [{ selector: 'updatedAt', desc: true }],
    permissions: {
      canAdd: false,
      canEdit: false,
      canDelete: false,
    },
  });

  return <SmartCrudPage resource={resource} />;
};
