import { useQuery } from '@tanstack/react-query';
import { Alert, Button, KpiMetricRow } from '@nubitio/ui';
import { httpClient } from '@shared/api/HttpClient';
import { formatMoney } from '@shared/lib/displayFormat';
import { saleEndpoints } from './api/saleEndpoints';
import { receivableAging, type ReceivableSummary } from './receivableAging';

async function fetchReceivableSummary(branchId?: string): Promise<ReceivableSummary> {
  const response = await httpClient.get<ReceivableSummary>(
    saleEndpoints.receivableSummary(branchId),
    {
      errorPresentation: 'inline',
    },
  );
  return response.data;
}

export const ReceivableSummaryKpis = ({
  refreshKey = 0,
  branchId,
}: {
  refreshKey?: number;
  branchId?: string;
}) => {
  const { data, isLoading, isError, isFetching, refetch } = useQuery({
    queryKey: ['sales', 'accounts-receivable', 'summary', branchId, refreshKey],
    queryFn: () => fetchReceivableSummary(branchId),
    retry: false,
  });
  const buckets = data ? receivableAging(data) : [];
  const total = buckets.reduce((sum, bucket) => sum + Number(bucket.amount), 0);

  return (
    <section
      className="accounts-receivable-page__summary"
      aria-label="Resumen de cuentas por cobrar"
    >
      {isError ? (
        <Alert tone="danger">
          No se pudo cargar el resumen de cuentas por cobrar.{' '}
          <Button
            variant="secondary"
            size="sm"
            disabled={isFetching}
            onClick={() => void refetch()}
          >
            Reintentar resumen
          </Button>
        </Alert>
      ) : (
        <>
          <p className="accounts-receivable-page__balance" aria-live="polite">
            {data
              ? `${formatMoney(data.totalBalance, data.currency)} pendientes de ${data.debtorCount} ${data.debtorCount === 1 ? 'cliente' : 'clientes'}`
              : 'Cargando resumen…'}
          </p>
          <KpiMetricRow
            testId="accounts-receivable-kpis"
            layout="cards"
            loading={isLoading}
            items={
              data
                ? buckets.map((bucket) => ({
                    label: bucket.label,
                    value: formatMoney(bucket.amount, data.currency),
                    tone: bucket.tone,
                  }))
                : ['Al día', 'Vencido 1–30 días', 'Vencido 31–60 días', 'Más de 60 días'].map(
                    (label) => ({ label, value: '—' }),
                  )
            }
          />
          {data && total > 0 && (
            <div className="accounts-receivable-page__aging-bar" aria-hidden="true">
              {buckets.map((bucket, index) => (
                <span
                  key={bucket.label}
                  data-bucket={index}
                  style={{ flexGrow: Number(bucket.amount) / total }}
                />
              ))}
            </div>
          )}
        </>
      )}
    </section>
  );
};
