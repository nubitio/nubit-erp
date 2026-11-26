import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Button, KpiMetricRow, SelectField } from '@nubitio/ui';
import { getCoreApiBaseUrl } from '@nubitio/core';
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
  const [requestedBranchId, setRequestedBranchId] = useState('');
  const options = useQuery({
    queryKey: ['reporting-branches', branchId],
    queryFn: async () =>
      (
        await httpClient.get<{ id: string; name: string; isActive: boolean }[]>(
          `${getCoreApiBaseUrl()}auth/reporting-branches`,
        )
      ).data,
  });
  const selectedBranch = options.data?.find((branch) => branch.id === requestedBranchId);
  const reportBranchId = selectedBranch?.id ?? branchId;
  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ['sales', 'monthly-summary', reportBranchId],
    queryFn: async () => {
      const url = saleEndpoints.monthlySummary();
      const query = selectedBranch
        ? `?${new URLSearchParams({ branchId: selectedBranch.id })}`
        : '';
      return (await httpClient.get<MonthlySalesSummary>(url + query)).data;
    },
    enabled: Boolean(reportBranchId),
    retry: false,
  });

  if (!branchId && !options.data?.length) return null;
  const period = data
    ? new Intl.DateTimeFormat('es-PE', { month: 'long', year: 'numeric' }).format(
        new Date(`${data.periodStart}T12:00:00`),
      )
    : 'Mes actual';
  return (
    <section className="sales-monthly-summary" aria-label="Resumen mensual de ventas">
      <div className="sales-monthly-summary__heading">
        <h3 title="Ventas no anuladas del local seleccionado para el resumen.">
          Resumen de {period}
        </h3>
        {options.data?.some((branch) => branch.id !== branchId) && (
          <SelectField
            aria-label="Sucursales autorizadas para el resumen"
            value={selectedBranch?.id ?? ''}
            onChange={(event) => setRequestedBranchId(event.target.value)}
          >
            <option value="">{branchId ? 'Sucursal operativa' : 'Selecciona una sucursal'}</option>
            {options.data
              .filter((branch) => branch.id !== branchId)
              .map((branch) => (
                <option key={branch.id} value={branch.id}>
                  {branch.isActive ? branch.name : `${branch.name} (inactiva)`}
                </option>
              ))}
          </SelectField>
        )}
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
