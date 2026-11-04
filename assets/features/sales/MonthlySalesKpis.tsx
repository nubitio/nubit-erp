import { useQuery } from '@tanstack/react-query';
import { Button, KpiMetricRow } from '@nubitio/ui';
import { httpClient } from '@shared/api/HttpClient';
import { formatMoney } from '@shared/lib/displayFormat';
import { saleEndpoints } from './api/saleEndpoints';

export interface MonthlySalesSummary {
  periodStart: string;
  periodEnd: string;
  currencies: {
    currency: string;
    sold: string;
    balanceDue: string;
    saleCount: number;
    sunatPending: number;
  }[];
}

export const MonthlySalesKpis = ({ branchId }: { branchId?: string | null }) => {
  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ['sales', 'monthly-summary', branchId],
    queryFn: async () =>
      (await httpClient.get<MonthlySalesSummary>(saleEndpoints.monthlySummary())).data,
    enabled: Boolean(branchId),
    retry: false,
  });

  if (!branchId) return null;
  const period = data
    ? new Intl.DateTimeFormat('es-PE', { month: 'long', year: 'numeric' }).format(
        new Date(`${data.periodStart}T12:00:00`),
      )
    : 'Mes actual';
  return (
    <section className="sales-monthly-summary" aria-label="Resumen mensual de ventas">
      <div className="sales-monthly-summary__heading">
        <h3 title="Sucursal actual · Ventas no anuladas. Independiente de los filtros de la lista.">
          Resumen de {period}
        </h3>
        {data?.currencies.length === 0 && (
          <span className="sales-monthly-summary__empty">
            Sin ventas este mes en esta sucursal.
          </span>
        )}
        <Button
          size="sm"
          variant="ghost"
          onClick={() => void refetch()}
          disabled={isLoading}
          aria-label="Actualizar resumen"
        >
          Actualizar
        </Button>
      </div>
      {isError ? (
        <p role="alert">No se pudo cargar el resumen. Usa «Actualizar» para reintentar.</p>
      ) : isLoading ? (
        <KpiMetricRow
          loading
          items={[
            { label: 'Vendido', value: '—' },
            { label: 'Por cobrar', value: '—' },
            { label: 'Ventas', value: '—' },
          ]}
        />
      ) : (
        data?.currencies.map((item) => (
          <div key={item.currency} className="sales-monthly-summary__currency">
            <KpiMetricRow
              layout="strip"
              density="compact"
              testId={`sales-kpis-${item.currency}`}
              items={[
                {
                  label: `Vendido (${item.currency})`,
                  value: formatMoney(item.sold, item.currency),
                },
                {
                  label: `Por cobrar (${item.currency})`,
                  value: formatMoney(item.balanceDue, item.currency),
                  hint: 'Saldo actual de las ventas emitidas este mes',
                },
                { label: `Ventas (${item.currency})`, value: item.saleCount },
                {
                  label: `Pendientes SUNAT (${item.currency})`,
                  value: item.sunatPending,
                  tone: item.sunatPending > 0 ? 'warning' : 'default',
                  hint: 'Comprobantes del mes sin aceptación: incluye rechazos y errores',
                },
              ]}
            />
          </div>
        ))
      )}
    </section>
  );
};
