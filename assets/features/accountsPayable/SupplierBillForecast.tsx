import { Link } from 'react-router-dom';
import { formatDate, formatMoney } from '@shared/lib/displayFormat';
import { hasPermission } from '@shared/permissions';
import './SupplierBillForecast.scss';
import { useSupplierBillForecast } from './useSupplierBillForecast';
import { useSupplierBillSummary } from './useSupplierBillSummary';

/** Figma "Cuentas por pagar": "Próximos 30 días" outlook and the receivables nudge. */
export function SupplierBillForecast() {
  const { data, isPending, isError } = useSupplierBillForecast();
  const { data: summary } = useSupplierBillSummary();
  if (isError || isPending) return null;

  const money = (value: string) => formatMoney(value, data.currency);
  const overdueReceivables = Number(data.overdueReceivables);
  const dueInSevenDays = Number(
    summary?.currencies.find((row) => row.currency === data.currency)?.dueWithinSevenDays ?? 0,
  );
  const canSeeReceivables = hasPermission('sales.view');
  const horizonEnd = new Date(`${data.asOf}T00:00:00`);
  horizonEnd.setDate(horizonEnd.getDate() + data.horizonDays);

  return (
    <section className="supplier-bill-forecast" aria-label={`Próximos ${data.horizonDays} días`}>
      <div className="supplier-bill-forecast__card">
        <h2>Próximos {data.horizonDays} días</h2>
        <dl>
          <div>
            <dt>Saldo en caja</dt>
            <dd>{money(data.cashBalance)}</dd>
          </div>
          <div>
            <dt>Cobros esperados (por vencer)</dt>
            <dd className="is-positive">+ {money(data.expectedReceipts)}</dd>
          </div>
          <div>
            <dt>Pagos a proveedores</dt>
            <dd className="is-negative">− {money(data.payables)}</dd>
          </div>
        </dl>
        <div className="supplier-bill-forecast__total">
          <span>Saldo estimado al {formatDate(horizonEnd.toISOString().slice(0, 10))}</span>
          <strong data-testid="payables-projected-balance">{money(data.projectedBalance)}</strong>
        </div>
        <p>
          Incluye solo el efectivo de las cajas abiertas, sin saldos bancarios. No incluye sueldos,
          alquiler ni impuestos del mes.
        </p>
      </div>
      {canSeeReceivables && overdueReceivables > 0 && (
        <aside className="supplier-bill-forecast__nudge" data-testid="payables-receivables-nudge">
          <h3>Lo vencido por cobrar ayuda con lo urgente</h3>
          <p>
            Tus clientes te deben {money(data.overdueReceivables)} vencidos
            {data.overdueReceivablesCount > 0 &&
              ` en ${data.overdueReceivablesCount} ${data.overdueReceivablesCount === 1 ? 'venta' : 'ventas'}`}
            {dueInSevenDays > 0 &&
              ` y pagas ${money(String(dueInSevenDays))} en los próximos 7 días`}
            .
          </p>
          <Link to="/accounts-receivable">Ir a cuentas por cobrar →</Link>
        </aside>
      )}
    </section>
  );
}
