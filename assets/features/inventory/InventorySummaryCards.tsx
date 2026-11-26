import { KpiMetricRow, type KpiMetricItem } from '@nubitio/ui';
import { useInventorySummary } from './useInventorySummary';

interface InventorySummaryCardsProps {
  warehouseId: string | null;
  branchId?: string | null;
}

function formatCurrency(value: string): string {
  const amount = Number(value);
  if (!Number.isFinite(amount)) return 'S/ 0.00';
  return amount.toLocaleString('es-PE', { style: 'currency', currency: 'PEN' });
}

const CARD_CONFIG = [
  { key: 'skus', label: 'SKUs activos', icon: 'ph-cube', tone: 'default' as const },
  { key: 'value', label: 'Valor de stock', icon: 'ph-coins', tone: 'default' as const },
  { key: 'low', label: 'Bajo mínimo', icon: 'ph-warning', tone: 'warning' as const },
  { key: 'out', label: 'Sin stock', icon: 'ph-prohibit', tone: 'danger' as const },
];

export const InventorySummaryCards = ({ warehouseId, branchId }: InventorySummaryCardsProps) => {
  const { data, isLoading } = useInventorySummary(warehouseId, branchId);

  const values: Record<string, { value: string; hint: string; emphasize: boolean }> = {
    skus: {
      value: data ? String(data.totalSkus) : '—',
      hint: data ? `${data.categoryCount} categorías` : '',
      emphasize: true,
    },
    value: {
      value: data ? formatCurrency(data.stockValueAtCost) : '—',
      hint: 'A precio de costo',
      emphasize: true,
    },
    low: {
      value: data ? String(data.lowStockCount) : '—',
      hint: 'Productos en alerta',
      emphasize: Boolean(data && data.lowStockCount > 0),
    },
    out: {
      value: data ? String(data.outOfStockCount) : '—',
      hint: 'Requieren atención',
      emphasize: Boolean(data && data.outOfStockCount > 0),
    },
  };

  const items: KpiMetricItem[] = CARD_CONFIG.map((card) => ({
    label: card.label,
    value: values[card.key].value,
    hint: values[card.key].hint || undefined,
    icon: card.icon,
    tone: card.tone === 'default' ? 'default' : values[card.key].emphasize ? card.tone : 'default',
  }));

  return (
    <KpiMetricRow
      layout="strip"
      density="compact"
      items={items}
      loading={isLoading}
      testId="inventory-summary"
    />
  );
};
