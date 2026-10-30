import { Alert, Button, KpiMetricRow } from '@nubitio/ui';
import { formatDate, formatMoney } from '@shared/lib/displayFormat';
import './SupplierBillSummary.scss';

import { useSupplierBillSummary } from './useSupplierBillSummary';

export function SupplierBillSummary() {
  const { data, isPending, isError, isFetching, refetch } = useSupplierBillSummary();
  return (
    <section className="supplier-bill-summary" aria-label="Resumen de cuentas por pagar">
      {isError ? (
        <Alert tone="danger">
          No se pudo cargar el resumen de cuentas por pagar.{' '}
          <Button
            variant="secondary"
            size="sm"
            disabled={isFetching}
            onClick={() => void refetch()}
          >
            Reintentar resumen
          </Button>
        </Alert>
      ) : isPending ? (
        <p role="status">Cargando vencimientos…</p>
      ) : (
        <>
          <div className="supplier-bill-summary__heading">
            <p>Vencimientos de la sucursal · {formatDate(data.asOf)}</p>
            <Button variant="ghost" size="sm" disabled={isFetching} onClick={() => void refetch()}>
              Actualizar resumen
            </Button>
          </div>
          {data.currencies.length === 0 ? (
            <p role="status">No hay saldos pendientes con proveedores.</p>
          ) : (
            data.currencies.map((summary) => (
              <div key={summary.currency} className="supplier-bill-summary__currency">
                <p>
                  {summary.currency} · {summary.billCount}{' '}
                  {summary.billCount === 1 ? 'factura' : 'facturas'} · {summary.supplierCount}{' '}
                  {summary.supplierCount === 1 ? 'proveedor' : 'proveedores'}
                </p>
                <KpiMetricRow
                  layout="cards"
                  items={[
                    {
                      label: 'Saldo pendiente',
                      value: formatMoney(summary.balanceDue, summary.currency),
                    },
                    {
                      label: 'Vencido',
                      value: formatMoney(summary.overdueTotal, summary.currency),
                      tone: Number(summary.overdueTotal) > 0 ? 'danger' : 'default',
                    },
                    {
                      label: 'Vence en 7 días',
                      value: formatMoney(summary.dueWithinSevenDays, summary.currency),
                      tone: Number(summary.dueWithinSevenDays) > 0 ? 'warning' : 'default',
                    },
                    {
                      label: 'Vence en 30 días',
                      value: formatMoney(summary.dueWithinThirtyDays, summary.currency),
                    },
                  ]}
                />
              </div>
            ))
          )}
          <p className="supplier-bill-summary__hint">
            Los próximos vencimientos incluyen hoy y excluyen lo ya vencido. El resumen abarca todas
            las facturas pendientes de la sucursal; los filtros inferiores afectan al listado.
          </p>
        </>
      )}
    </section>
  );
}
