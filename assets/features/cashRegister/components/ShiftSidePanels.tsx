import NumberUtils from '@shared/lib/NumberUtils';
import type { XReportDto } from '../services/cashRegisterService';
import { formatSignedMoney } from '../model/format';
import { Skeleton } from '@nubitio/ui';

// One colour per payment method, in the order the report lists them; credit is always the neutral one.
const METHOD_COLORS = ['#2563eb', '#b45309', '#15803d', '#a16207', '#a21caf', '#0e7490'];

const money = (value: number) => NumberUtils.formatCurrency(value);
const num = (value: string | number | null | undefined) => Number(value ?? 0);

/**
 * How the drawer's expected cash is made up. The cash sales are derived from the balance so the lines always add
 * up to the total, whatever the report calls each of them.
 */
export function expectedCashLines(report: XReportDto) {
  const opening = num(report.openingBalance);
  const income = num(report.cashIn);
  const outflow = num(report.cashOut);
  const withdrawals = Math.min(num(report.withdrawalsOut), outflow);
  const expected = num(report.currentBalance);

  return {
    opening,
    cashSales: expected - opening - income + outflow,
    income,
    expenses: outflow - withdrawals,
    withdrawals,
    expected,
  };
}

function Panel({
  title,
  aside,
  children,
}: {
  title: string;
  aside?: string;
  children: React.ReactNode;
}) {
  return (
    <section className="shift-panel">
      <header className="shift-panel__header">
        <h3>{title}</h3>
        {aside && <strong>{aside}</strong>}
      </header>
      {children}
    </section>
  );
}

const Line = ({ label, value, negative }: { label: string; value: string; negative?: boolean }) => (
  <div className="shift-panel__line">
    <dt>{label}</dt>
    <dd className={negative ? 'is-negative' : undefined}>{value}</dd>
  </div>
);

/** The side of the shift screen: expected cash, sales by payment method and receipts issued. */
export function ShiftSidePanels({ report }: { report: XReportDto | undefined }) {
  if (!report) {
    return (
      <aside className="shift-side">
        <Panel title="Efectivo esperado">
          <Skeleton variant="text" lines={5} />
        </Panel>
      </aside>
    );
  }

  const lines = expectedCashLines(report);
  const total = num(report.totalSales);
  const methods = report.salesByPaymentMethod;
  const credit = num(report.receivableTotal);
  const documents = report.documents ?? { boletas: 0, facturas: 0, creditNotes: 0 };

  return (
    <aside className="shift-side">
      <Panel title="Efectivo esperado">
        <div className="shift-panel__big" data-testid="shift-expected-cash">
          {money(lines.expected)}
        </div>
        <dl className="shift-panel__lines">
          <Line label="Fondo inicial" value={money(lines.opening)} />
          <Line label="Ventas en efectivo" value={money(lines.cashSales)} />
          <Line label="Ingresos" value={money(lines.income)} />
          <Line
            label="Egresos"
            value={formatSignedMoney(-lines.expenses)}
            negative={lines.expenses > 0}
          />
          <Line
            label="Retiros de efectivo"
            value={formatSignedMoney(-lines.withdrawals)}
            negative={lines.withdrawals > 0}
          />
          <div className="shift-panel__line is-total">
            <dt>Efectivo esperado</dt>
            <dd>{money(lines.expected)}</dd>
          </div>
        </dl>
      </Panel>

      <Panel title="Ventas por medio de pago" aside={money(total)}>
        {methods.length === 0 && credit === 0 ? (
          <p className="shift-panel__empty">Todavía no hay ventas en este turno.</p>
        ) : (
          <ul className="shift-bars">
            {methods.map((method, index) => (
              <li key={method.paymentMethodId}>
                <div className="shift-bars__row">
                  <span>{method.paymentMethod}</span>
                  <span>{money(num(method.amount))}</span>
                </div>
                <div className="shift-bars__track" aria-hidden="true">
                  <div
                    className="shift-bars__fill"
                    style={{
                      width: `${total > 0 ? Math.min(100, (num(method.amount) / total) * 100) : 0}%`,
                      background: METHOD_COLORS[index % METHOD_COLORS.length],
                    }}
                  />
                </div>
              </li>
            ))}
            {credit > 0 && (
              <li>
                <div className="shift-bars__row">
                  <span>Crédito (por cobrar)</span>
                  <span>{money(credit)}</span>
                </div>
                <div className="shift-bars__track" aria-hidden="true">
                  <div
                    className="shift-bars__fill is-credit"
                    style={{ width: `${total > 0 ? Math.min(100, (credit / total) * 100) : 0}%` }}
                  />
                </div>
              </li>
            )}
          </ul>
        )}
        <p className="shift-panel__note">
          Solo el efectivo se cuenta en la caja. Tarjeta, Yape, Plin y transferencia se concilian al
          cerrar el turno; el crédito pasa a cuentas por cobrar.
        </p>
      </Panel>

      <Panel title="Comprobantes emitidos">
        <dl className="shift-panel__lines">
          <Line label="Boletas" value={String(documents.boletas)} />
          <Line label="Facturas" value={String(documents.facturas)} />
          <Line label="Notas de crédito" value={String(documents.creditNotes)} />
        </dl>
      </Panel>
    </aside>
  );
}
