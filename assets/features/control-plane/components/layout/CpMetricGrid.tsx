import { KpiMetricRow, type KpiMetricItem } from '@nubitio/ui';

interface CpMetricGridProps {
  items: KpiMetricItem[];
  layout?: 'cards' | 'strip';
  density?: 'default' | 'compact';
  loading?: boolean;
  className?: string;
}

export const CpMetricGrid = ({
  items,
  layout = 'cards',
  density = 'default',
  loading,
  className,
}: CpMetricGridProps) => (
  <KpiMetricRow
    items={items}
    layout={layout}
    density={density}
    loading={loading}
    className={className}
  />
);
