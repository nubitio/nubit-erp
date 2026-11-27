import { useQuery } from '@tanstack/react-query';
import { Alert, Button } from '@nubitio/ui';
import { httpClient } from '@shared/api/HttpClient';
import { formatMoney } from '@shared/lib/displayFormat';
import { saleEndpoints } from './api/saleEndpoints';
import { receivableAging, type ReceivableSummary } from './receivableAging';
import './AccountsReceivablePage.scss';

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
        <div className="accounts-receivable-page__summary-content" aria-live="polite">
          <div className="accounts-receivable-page__total">
            <span>Saldo total por cobrar</span>
            <strong>{data ? formatMoney(data.totalBalance, data.currency) : '—'}</strong>
            <small>
              {isLoading
                ? 'Cargando clientes…'
                : `${data?.debtorCount ?? 0} ${data?.debtorCount === 1 ? 'cliente' : 'clientes'}`}
            </small>
          </div>
          <div className="accounts-receivable-page__aging">
            <div className="accounts-receivable-page__aging-bar" aria-hidden="true">
              {buckets.map((bucket, index) => (
                <span
                  key={bucket.label}
                  data-bucket={index}
                  style={{ flexGrow: Number(bucket.amount) || 0 }}
                />
              ))}
            </div>
            <div className="accounts-receivable-page__aging-values">
              {buckets.map((bucket, index) => (
                <div key={bucket.label} data-bucket={index}>
                  <span>{['Al día', '1–30 días', '31–60 días', '+60 días'][index]}</span>
                  <strong>{data ? formatMoney(bucket.amount, data.currency) : '—'}</strong>
                </div>
              ))}
            </div>
          </div>
        </div>
      )}
    </section>
  );
};
