import NumberUtils from '@shared/lib/NumberUtils';
import type { CashRegisterSummaryDto } from '../services/cashboxService';
import type { RegistersSummary } from '../model/registersSummary';
import { formatSignedMoney } from '../model/format';

interface KpiProps {
  label: string;
  value: string;
  hint: string;
  tone?: 'danger';
  testId: string;
}

const Kpi = ({ label, value, hint, tone, testId }: KpiProps) => (
  <div className="cashbox-kpi" data-testid={testId}>
    <div className="cashbox-kpi__label">{label}</div>
    <div className={`cashbox-kpi__value${tone ? ` is-${tone}` : ''}`}>{value}</div>
    <div className="cashbox-kpi__hint">{hint}</div>
  </div>
);

const plural = (count: number, one: string, many: string) => `${count} ${count === 1 ? one : many}`;

interface Props {
  totals: RegistersSummary;
  summary: CashRegisterSummaryDto | undefined;
}

/** The four figures at the top of "Cajas y turnos". A figure the server has not sent yet shows a dash. */
export function CashboxKpis({ totals, summary }: Props) {
  const difference = summary ? Number(summary.closureDifference7Days) : null;

  return (
    <section className="cashbox-kpis" aria-label="Resumen de cajas" data-testid="cashbox-kpis">
      <Kpi
        testId="cashbox-kpi-cash"
        label="Efectivo esperado en cajas"
        value={NumberUtils.formatCurrency(totals.expectedCash)}
        hint={plural(totals.open, 'turno abierto', 'turnos abiertos')}
      />
      <Kpi
        testId="cashbox-kpi-sales"
        label="Ventas de hoy"
        value={summary ? NumberUtils.formatCurrency(Number(summary.salesToday)) : '—'}
        hint={
          summary
            ? plural(
                summary.registersWithShiftToday,
                'caja con turno de hoy',
                'cajas con turno de hoy',
              )
            : ' '
        }
      />
      <Kpi
        testId="cashbox-kpi-open"
        label="Cajas abiertas"
        value={`${totals.open} de ${totals.total}`}
        hint={
          totals.stale.length > 0
            ? `${totals.stale.length} con arqueo pendiente`
            : 'Sin arqueos pendientes'
        }
      />
      <Kpi
        testId="cashbox-kpi-difference"
        label="Diferencias de cierre (7 días)"
        value={difference === null ? '—' : formatSignedMoney(difference)}
        tone={difference !== null && difference < 0 ? 'danger' : undefined}
        hint={
          summary
            ? summary.shortageShifts7Days > 0
              ? plural(summary.shortageShifts7Days, 'turno con faltante', 'turnos con faltante')
              : 'Sin faltantes'
            : ' '
        }
      />
    </section>
  );
}
