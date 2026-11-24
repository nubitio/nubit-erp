import type { ReactNode } from 'react';
import type { ShiftReportData } from '../services/cashSessionService';
import { formatDate, formatDateTime, shiftCode } from '../model/format';

const number = (value: string | number) =>
  Number(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function Row({ label, value, strong }: { label: ReactNode; value: ReactNode; strong?: boolean }) {
  return (
    <div className={`shift-ticket__row${strong ? ' is-strong' : ''}`}>
      <span>{label}</span>
      <span>{value}</span>
    </div>
  );
}

const Section = ({ title, children }: { title: string; children: ReactNode }) => (
  <section className="shift-ticket__section">
    <h4>{title}</h4>
    {children}
  </section>
);

/** The shift report as a till ticket: X while the shift is open (informative), Z once it is closed. */
export function ShiftTicket({ report }: { report: ShiftReportData }) {
  const { shift, company, documents, sales, cash } = report;
  const closed = shift.status === 'closed';
  const difference = cash.difference === null ? null : Number(cash.difference);
  const differenceText =
    difference === null
      ? null
      : difference === 0
        ? 'S/ 0.00 CUADRADO'
        : `S/ ${number(Math.abs(difference))} ${difference < 0 ? 'FALTANTE' : 'SOBRANTE'}`;

  return (
    <article className="shift-ticket" data-testid="shift-ticket">
      <header className="shift-ticket__head">
        <strong>{company.name ?? 'Nubit ERP'}</strong>
        {company.ruc && <div>RUC {company.ruc}</div>}
        {company.branch && <div>{company.branch}</div>}
      </header>

      <div className="shift-ticket__title">
        <strong>{closed ? 'REPORTE Z' : 'REPORTE X'}</strong>
        <strong>{closed ? 'CIERRE DE TURNO' : 'CORTE PARCIAL'}</strong>
      </div>

      <div className="shift-ticket__block">
        <Row label="Caja" value={shift.registerName} />
        <Row label="Turno" value={shiftCode(shift.id)} />
        {shift.cashier && <Row label="Cajero" value={shift.cashier} />}
        {shift.openedAt && <Row label="Apertura" value={formatDateTime(shift.openedAt)} />}
        {closed && shift.closedAt ? (
          <Row label="Cierre" value={formatDateTime(shift.closedAt)} />
        ) : (
          <Row label="Emisión" value={formatDateTime(new Date())} />
        )}
        {closed && <Row label="N.° Z" value={`Z-${String(shift.id).padStart(7, '0')}`} />}
        {shift.closedBy && <Row label="Cerrado por" value={shift.closedBy} />}
      </div>

      <Section title="VENTAS POR COMPROBANTE">
        {documents.rows.length === 0 && <p className="shift-ticket__muted">Sin comprobantes.</p>}
        {documents.rows.map((row) => (
          <Row key={row.type} label={`${row.label} (${row.count})`} value={number(row.total)} />
        ))}
        <Row strong label="TOTAL VENTAS" value={number(sales.total)} />
        <Row label="Op. gravada" value={number(documents.taxable)} />
        <Row label="IGV (18 %)" value={number(documents.igv)} />
        {closed &&
          documents.rows.flatMap((row) =>
            row.ranges.map((range) => (
              <p key={`${row.type}-${range}`} className="shift-ticket__muted">
                {range}
              </p>
            )),
          )}
      </Section>

      <Section title="VENTAS POR MEDIO DE PAGO">
        {sales.byMethod.map((method) => (
          <Row key={method.name} label={method.name} value={number(method.amount)} />
        ))}
        {Number(sales.credit.amount) > 0 && (
          <Row label="Crédito" value={number(sales.credit.amount)} />
        )}
      </Section>

      <Section title={closed ? 'ARQUEO DE EFECTIVO' : 'EFECTIVO EN CAJA'}>
        <Row label="Fondo inicial" value={number(cash.opening)} />
        <Row label="+ Ventas en efectivo" value={number(cash.cashSales)} />
        <Row label="+ Ingresos" value={number(cash.income)} />
        <Row label="- Egresos" value={number(cash.expenses)} />
        <Row label="- Retiros" value={number(cash.withdrawals)} />
        <Row
          strong
          label={closed ? 'Esperado' : 'EFECTIVO ESPERADO'}
          value={number(cash.expected)}
        />
        {closed && cash.counted !== null && (
          <Row strong label="Contado" value={number(cash.counted)} />
        )}
        {closed && differenceText && <Row strong label="DIFERENCIA" value={differenceText} />}
        {closed && difference !== null && difference !== 0 && cash.note && (
          <p className="shift-ticket__muted">Motivo: {cash.note}</p>
        )}
      </Section>

      {closed && (
        <Section title="MEDIOS SIN EFECTIVO">
          <p className="shift-ticket__muted">
            Tarjeta, Yape, Plin y transferencia se concilian con el terminal, la app o el banco.
          </p>
        </Section>
      )}

      {closed && cash.fundKept !== null && cash.withdrawnAtClose !== null && (
        <Section title="DESTINO DEL EFECTIVO">
          <Row label="Fondo siguiente turno" value={number(cash.fundKept ? cash.opening : 0)} />
          <Row label="Retirado de la caja" value={number(cash.withdrawnAtClose)} />
        </Section>
      )}

      {closed ? (
        <footer className="shift-ticket__signatures">
          <div>
            <span />
            Cajero
          </div>
          <div>
            <span />
            Supervisor
          </div>
        </footer>
      ) : (
        <footer className="shift-ticket__foot">
          <strong>REPORTE INFORMATIVO</strong>
          <div>No cierra el turno ni reinicia contadores.</div>
        </footer>
      )}
      <p className="shift-ticket__muted shift-ticket__stamp">
        {formatDate(new Date())} · Nubit ERP
      </p>
    </article>
  );
}
