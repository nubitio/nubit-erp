import React, { useMemo, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  FieldType,
  SmartCrudPage,
  defineFields,
  defineResource,
  type DataRecord,
  type GridCellContext,
} from '@nubitio/crud';
import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();

// ─── Types ───────────────────────────────────────────────────────────────────

interface ReorderRecord extends DataRecord {
  id: string;
  product: { id?: string; name?: string; code?: string | null } | null;
  warehouse: { id?: string; name?: string } | null;
  qtyOnHand: string;
  qtyReserved: string;
  qtyAvailable: string;
  minStock: string | null;
  updatedAt: string | null;
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

const formatQty = (value: unknown): string => {
  const n = Number(value ?? 0);
  return Number.isFinite(n)
    ? n.toLocaleString('es-PE', { maximumFractionDigits: 3, minimumFractionDigits: 0 })
    : '';
};

const formatName = (value: unknown): string => {
  if (value && typeof value === 'object') {
    const p = value as { code?: string | null; name?: string };
    return [p.code, p.name].filter(Boolean).join(' - ');
  }
  return String(value ?? '');
};

/** Coverage = stock / minStock as a percentage, clamped 0–100 */
const coverage = (stock: string, minStock: string): number => {
  const s = parseFloat(stock);
  const m = parseFloat(minStock);
  if (!m || m <= 0) return 100;
  return Math.min(100, Math.round((s / m) * 100));
};

function CoverageCell({ data }: GridCellContext) {
  const pct = coverage(String(data?.qtyOnHand ?? '0'), String(data?.minStock ?? '0'));
  const color = pct <= 0 ? '#ef4444' : pct < 30 ? '#f97316' : '#eab308';
  return (
    <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
      <span
        style={{
          display: 'inline-block',
          width: 60,
          height: 6,
          background: '#e5e7eb',
          borderRadius: 3,
          overflow: 'hidden',
        }}
      >
        <span
          style={{
            display: 'block',
            width: `${pct}%`,
            height: '100%',
            background: color,
            borderRadius: 3,
            transition: 'width 0.3s',
          }}
        />
      </span>
      <span style={{ fontSize: 12, color, fontWeight: 700 }}>{pct}%</span>
    </span>
  );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

const API_URL = `${API}stock-items?belowMin=1`;

const REORDER_COLUMN_PRESETS = [
  {
    key: 'basic',
    label: 'Básico',
    columns: ['product', 'warehouse', 'qtyOnHand', 'minStock', '_coverage', '_actions'],
  },
  {
    key: 'full',
    label: 'Completo',
    columns: ['product', 'warehouse', 'qtyOnHand', 'minStock', '_coverage', 'updatedAt', '_actions'],
  },
];

export const ReorderPage = () => {
  const navigate = useNavigate();

  const makeActionsCell = useCallback(
    () =>
      function ActionsCell({ data }: GridCellContext) {
        const product = data?.product;
        const productId = product && typeof product === 'object'
          ? (product as { id?: string }).id
          : String(product ?? '');

        const goKardex = (e: React.MouseEvent) => {
          e.stopPropagation();
          const params = new URLSearchParams();
          if (productId) params.set('product', productId);
          navigate(`/kardex?${params.toString()}`);
        };

        const goReceipt = (e: React.MouseEvent) => {
          e.stopPropagation();
          navigate('/receipt');
        };

        return (
          <span style={{ display: 'inline-flex', gap: 6 }}>
            <button onClick={goKardex} title="Ver Kardex" style={btnStyle}>
              <i className="ph ph-list-magnifying-glass" />
            </button>
            <button onClick={goReceipt} title="Crear entrada" style={{ ...btnStyle, color: '#16a34a' }}>
              <i className="ph ph-plus-circle" />
            </button>
          </span>
        );
      },
    [navigate],
  );

  const resource = useMemo(
    () =>
      defineResource<ReorderRecord>(API_URL, {
        id: 'reorder-alerts',
        title: 'Alertas de reabastecimiento',
        fieldContract: defineFields<ReorderRecord>({
          source: 'hydra',
          strategy: 'augment',
          directives: [
            { kind: 'override', key: 'id', patch: { visible: false, visibleOnForm: false } },
            {
              kind: 'override',
              key: 'product',
              patch: { order: 0, width: 260, label: 'Producto', formatter: (e) => formatName(e.value) },
            },
            {
              kind: 'override',
              key: 'warehouse',
              patch: { order: 1, width: 180, label: 'Almacén', formatter: (e) => formatName(e.value) },
            },
            {
              kind: 'override',
              key: 'qtyOnHand',
              patch: {
                order: 2, width: 120, label: 'Stock actual', align: 'right',
                formatter: (e) => formatQty(e.value),
              },
            },
            {
              kind: 'override',
              key: 'minStock',
              patch: {
                order: 3, width: 120, label: 'Stock mínimo', align: 'right',
                formatter: (e) => formatQty(e.value),
              },
            },
            { kind: 'remove', key: 'qtyReserved' },
            { kind: 'remove', key: 'qtyAvailable' },
            {
              kind: 'override',
              key: 'updatedAt',
              patch: { order: 6, width: 160, label: 'Actualizado' },
            },
            {
              kind: 'append' as const,
              field: {
                name: '_coverage',
                type: FieldType.TEXT,
                label: 'Cobertura',
                order: 4,
                width: 130,
                formatter: CoverageCell,
              } as any,
            },
            {
              kind: 'append' as const,
              field: {
                name: '_actions',
                type: FieldType.TEXT,
                label: '',
                order: 5,
                width: 80,
                formatter: makeActionsCell(),
              } as any,
            },
          ],
        }),
        sort: [{ selector: 'qtyOnHand', desc: false }],
        permissions: { canAdd: false, canEdit: false, canDelete: false },
        defaultPreset: 'basic',
        columnPresets: REORDER_COLUMN_PRESETS,
        emptyState: {
          title: 'Sin alertas de reabastecimiento',
          description: 'Todos los productos tienen stock por encima del mínimo configurado.',
          icon: 'check-circle',
          variant: 'success',
        },
      }),
    [makeActionsCell],
  );

  return <SmartCrudPage resource={resource} />;
};

const btnStyle: React.CSSProperties = {
  background: 'none',
  border: '1px solid var(--base-border-color, #d1d5db)',
  borderRadius: 4,
  cursor: 'pointer',
  padding: '2px 6px',
  fontSize: 14,
  color: 'var(--accent-color, #3b82f6)',
  lineHeight: 1,
};
