import {
  Alert,
  Button,
  EmptyState,
  KpiMetricRow,
  Page,
  PageHeader,
  Skeleton,
  Tooltip,
} from '@nubitio/ui';
import { Link } from 'react-router-dom';
import { formatDate, formatMoney } from '@shared/lib/displayFormat';
import { useTenantRuntimeConfig } from '@shared/hooks/useTenantRuntimeConfig';
import { hasPermission } from '@shared/permissions';
import { treasuryErrorMessage, useFinanceSummary, type CurrencySummary } from '../api/treasuryApi';
import { attentionItems, currencyTitle } from './summaryModel';
import './FinanceSummaryPage.scss';

const PROJECTION_HELP =
  'Lo que tienes hoy, más los cobros que vencen en los próximos 30 días, menos los pagos que vencen en ese plazo y los que ya están vencidos. Los cobros vencidos no se cuentan porque no se sabe cuándo se pagarán.';

function CurrencySection({ summary }: { summary: CurrencySummary }) {
  const { currency, position, receivables, payables, projectedInThirtyDays } = summary;
  const clearing = position.clearing ?? '0.00';
  const money = (value: string) => formatMoney(value, currency);
  const owes = (value: string) => Number(value) > 0;

  return (
    <section className="finance-summary__currency" aria-label={currencyTitle(currency)}>
      <h2 className="finance-summary__currency-title">{currencyTitle(currency)}</h2>
      <KpiMetricRow
        layout="cards"
        items={[
          {
            label: 'Dinero disponible',
            value: money(position.total),
            hint: `Cajas ${money(position.cash)} · Bancos ${money(position.banks)} · Billeteras ${money(position.wallets)}${
              owes(position.suspense) || Number(position.suspense) < 0
                ? ` · Sin asignar ${money(position.suspense)}`
                : ''
            }`,
          },
          ...(Number(clearing) !== 0
            ? [{ label: 'Fondos por liquidar', value: money(clearing) }]
            : []),
          {
            label: 'Por cobrar',
            value: money(receivables.balance),
            tone: owes(receivables.overdue) ? 'warning' : 'default',
            hint: `Vencido ${money(receivables.overdue)} · Esta semana ${money(receivables.dueWithinSevenDays)}`,
          },
          {
            label: 'Por pagar',
            value: money(payables.balance),
            tone: owes(payables.overdue) ? 'danger' : 'default',
            hint: `Vencido ${money(payables.overdue)} · Esta semana ${money(payables.dueWithinSevenDays)}`,
          },
          {
            label: 'Proyección a 30 días',
            value: money(projectedInThirtyDays),
            tone: Number(projectedInThirtyDays) < 0 ? 'danger' : 'default',
            hint: (
              <Tooltip content={PROJECTION_HELP} side="bottom">
                <button type="button" className="finance-summary__help">
                  <i className="ph ph-info" aria-hidden /> Cómo se calcula
                </button>
              </Tooltip>
            ),
          },
        ]}
      />
    </section>
  );
}

/**
 * Resumen de finanzas: the owner's first screen. How much there is (cash, banks, wallets), who owes the
 * business and what it owes, a cautious 30-day projection, and what needs attention today, each item linking
 * to where it is solved. Everything is per currency and never converted.
 */
export const FinanceSummaryPage = () => {
  const config = useTenantRuntimeConfig();
  const enabled = config.data?.capabilities['treasury.accounts']?.enabled === true;
  const summary = useFinanceSummary(enabled);

  if (config.isPending) return <Skeleton height={240} />;
  if (!enabled) {
    return (
      <Page>
        <EmptyState
          icon="chart-line"
          title="El resumen de finanzas no está habilitado"
          description="Pide al administrador que active las cuentas de dinero para tu negocio."
        />
      </Page>
    );
  }

  const items = summary.data ? attentionItems(summary.data, hasPermission, formatMoney) : [];

  return (
    <Page wide className="finance-summary">
      <PageHeader
        title="Resumen de finanzas"
        subtitle={
          summary.data
            ? `Al ${formatDate(summary.data.asOf)} · todas las sucursales`
            : 'Cuánto tienes, cuánto te deben y qué necesita tu atención.'
        }
      />

      {summary.isPending && <Skeleton height={220} />}
      {summary.isError && (
        <Alert tone="danger">
          {treasuryErrorMessage(summary.error, 'No se pudo cargar el resumen.')}{' '}
          <Button variant="secondary" size="sm" onClick={() => void summary.refetch()}>
            Reintentar
          </Button>
        </Alert>
      )}

      {summary.data && summary.data.currencies.length === 0 && (
        <EmptyState
          icon="bank"
          title="Aún no hay datos de dinero"
          description="Sincroniza tus cuentas y registra su saldo inicial en Cuentas de dinero."
          action={
            <Link className="finance-summary__link" to="/treasury">
              Ir a Cuentas de dinero
            </Link>
          }
        />
      )}

      {summary.data?.currencies.map((row) => (
        <CurrencySection key={row.currency} summary={row} />
      ))}

      {summary.data && summary.data.currencies.length > 0 && (
        <section className="finance-summary__attention" aria-label="Qué atender">
          <h2 className="finance-summary__currency-title">Qué atender</h2>
          {items.length === 0 ? (
            <p className="finance-summary__ok">
              Todo al día: no hay nada que requiera tu atención.
            </p>
          ) : (
            <ul className="finance-summary__items">
              {items.map((item) => (
                <li
                  key={item.key}
                  className={`finance-summary__item finance-summary__item--${item.tone}`}
                >
                  <span>{item.label}</span>
                  <Link className="finance-summary__link" to={item.to}>
                    Ver
                  </Link>
                </li>
              ))}
            </ul>
          )}
        </section>
      )}
    </Page>
  );
};
