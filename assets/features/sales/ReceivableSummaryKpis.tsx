import { useQuery } from '@tanstack/react-query';
import { KpiMetricRow } from '@nubitio/ui';
import { httpClient } from '@shared/api/HttpClient';
import { formatMoney } from '@shared/lib/displayFormat';
import { saleEndpoints } from './api/saleEndpoints';

interface ReceivableSummary {
  totalBalance: string;
  currency: string;
  debtorCount: number;
  overdueTotal: string;
}

async function fetchReceivableSummary(): Promise<ReceivableSummary> {
  const response = await httpClient.get<ReceivableSummary>(saleEndpoints.receivableSummary());
  return response.data;
}

interface Props {
  refreshKey?: number;
}

export const ReceivableSummaryKpis = ({ refreshKey = 0 }: Props) => {
  const { data, isLoading } = useQuery({
    queryKey: ['sales', 'accounts-receivable', 'summary', refreshKey],
    queryFn: fetchReceivableSummary,
  });

  const currency = data?.currency ?? 'PEN';
  const hasOverdue = Number(data?.overdueTotal ?? 0) > 0;

  return (
    <div className="accounts-receivable-page__summary">
      <KpiMetricRow
        testId="accounts-receivable-kpis"
        layout="cards"
        loading={isLoading}
        items={[
          {
            label: 'Por cobrar',
            value: formatMoney(data?.totalBalance ?? '0', currency),
            icon: 'ph-hand-coins',
            tone: 'default',
          },
          {
            label: 'Vencido',
            value: formatMoney(data?.overdueTotal ?? '0', currency),
            icon: 'ph-warning-circle',
            tone: hasOverdue ? 'danger' : 'default',
          },
          {
            label: 'Clientes con deuda',
            value: data?.debtorCount ?? 0,
            icon: 'ph-users',
            tone: 'default',
          },
        ]}
      />
    </div>
  );
};
