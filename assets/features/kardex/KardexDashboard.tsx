import { useMemo } from 'react';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { EntityDropdown } from '@nubitio/crud';
import { DateRangePicker, HubPanel, KpiMetricRow, SegmentedControl, type KpiMetricItem } from '@nubitio/ui';
import { productEndpoints } from '@features/catalog/api/catalogEndpoints';
import { useKardexSummary } from './useKardexSummary';
import {
  formatCurrency,
  formatQty,
  valuationMethodLabel,
  type KardexValuationMethod,
} from './kardexShared';

const API = getCoreApiBaseUrl();

export interface KardexDashboardProps {
  productId: string | null;
  warehouseId: string | null;
  from: string | null;
  to: string | null;
  valuation: KardexValuationMethod;
  warehouseOptions: Array<{ value: string; label: string }>;
  onProductChange: (productId: string | null) => void;
  onWarehouseChange: (warehouseId: string | null) => void;
  onFromChange: (value: string | null) => void;
  onToChange: (value: string | null) => void;
  onValuationChange: (value: KardexValuationMethod) => void;
}

function formatProductLabel(item: unknown): string {
  const row = item as Record<string, unknown>;
  return [row.code, row.name].filter(Boolean).join(' - ');
}

export function KardexDashboard({
  productId,
  warehouseId,
  from,
  to,
  valuation,
  onProductChange,
  onWarehouseChange,
  onFromChange,
  onToChange,
  onValuationChange,
}: KardexDashboardProps) {
  const { data: summary, isLoading } = useKardexSummary(productId, warehouseId, from, to, valuation);

  const kpiItems = useMemo<KpiMetricItem[]>(() => {
    const unit = summary?.product.unitName ? ` ${summary.product.unitName}` : '';

    if (!summary) {
      return [
        { label: 'Stock actual', value: '—', icon: 'ph-package' },
        { label: 'Valor stock', value: '—', icon: 'ph-coins' },
        { label: valuation === 'fifo' ? 'Costo FIFO' : 'Costo prom.', value: '—', icon: 'ph-scales' },
        { label: 'Total entradas', value: '—', icon: 'ph-arrow-down-left', tone: 'success' },
        { label: 'Total salidas', value: '—', icon: 'ph-arrow-up-right', tone: 'danger' },
        { label: 'Movimientos', value: '—', icon: 'ph-list-numbers' },
      ];
    }

    return [
      {
        label: 'Stock actual',
        value: `${formatQty(summary.currentStock)}${unit}`,
        icon: 'ph-package',
      },
      {
        label: 'Valor stock',
        value: formatCurrency(summary.stockValue),
        icon: 'ph-coins',
      },
      {
        label: valuation === 'fifo' ? 'Costo FIFO' : 'Costo prom.',
        value: summary.avgUnitCost ? formatCurrency(summary.avgUnitCost) : '—',
        icon: 'ph-scales',
      },
      {
        label: 'Total entradas',
        value: formatQty(summary.totalIn),
        icon: 'ph-arrow-down-left',
        tone: 'success',
      },
      {
        label: 'Total salidas',
        value: formatQty(summary.totalOut),
        icon: 'ph-arrow-up-right',
        tone: 'danger',
      },
      {
        label: 'Movimientos',
        value: String(summary.movementCount),
        icon: 'ph-list-numbers',
      },
    ];
  }, [summary, valuation]);

  return (
    <HubPanel variant="flush">
      <div className="kardex-context-bar" data-testid="kardex-context-bar">
        <div className="kardex-context-bar__field">
          <span className="kardex-context-bar__label">Producto</span>
          <EntityDropdown
            url={productEndpoints.list()}
            value={productId}
            onChange={onProductChange}
            placeholder="Buscar producto…"
            searchExpr={['name', 'code']}
            itemFormatter={formatProductLabel}
          />
        </div>
        <div className="kardex-context-bar__field">
          <span className="kardex-context-bar__label">Almacén</span>
          <EntityDropdown
            url={`${API}warehouses`}
            value={warehouseId}
            onChange={onWarehouseChange}
            placeholder="Todos los almacenes"
            searchExpr={['name']}
          />
        </div>
        <div className="kardex-context-bar__field kardex-context-bar__field--dates">
          <span className="kardex-context-bar__label">Período</span>
          <DateRangePicker
            startValue={from}
            endValue={to}
            locale="es-PE"
            ariaLabel="Período"
            onChange={(start, end) => {
              onFromChange(start || null);
              onToChange(end || null);
            }}
          />
        </div>
        <div className="kardex-context-bar__field">
          <span className="kardex-context-bar__label">Valoración</span>
          <SegmentedControl
            value={valuation}
            onChange={onValuationChange}
            options={[
              { value: 'pp', label: 'Costo prom. (PP)' },
              { value: 'fifo', label: 'FIFO' },
            ]}
            ariaLabel="Método de valoración"
          />
        </div>
      </div>

      {productId && summary && (
        <div className="kardex-product-card" data-testid="kardex-product-card">
          <div className="kardex-product-card__icon" aria-hidden="true">
            <i className="ph ph-cube" />
          </div>
          <div className="kardex-product-card__body">
            <h3 className="kardex-product-card__name">{summary.product.name}</h3>
            <p className="kardex-product-card__meta">
              {[
                summary.product.code,
                summary.product.categoryName,
                summary.product.unitName ? `Unidad: ${summary.product.unitName}` : null,
              ]
                .filter(Boolean)
                .join(' · ')}
            </p>
          </div>
          <div className="kardex-product-card__aside">
            <span>Método: {valuationMethodLabel(valuation)}</span>
            <span>Período: {summary.periodLabel}</span>
          </div>
        </div>
      )}

      {productId ? (
        <KpiMetricRow
          layout="strip"
          density="compact"
          className="kardex-summary-kpis"
          items={kpiItems}
          loading={isLoading}
          testId="kardex-summary"
        />
      ) : (
        <div className="kardex-empty-prompt" data-testid="kardex-empty-prompt">
          <i className="ph ph-magnifying-glass" aria-hidden="true" />
          <p>Selecciona un producto para ver el kardex de movimientos.</p>
        </div>
      )}

    </HubPanel>
  );
}