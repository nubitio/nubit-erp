import { useMemo, type ReactNode } from 'react';
import { Link } from 'react-router-dom';
import { DateTime } from 'luxon';
import { AppToolbar, type BadgeProps } from '@nubitio/ui';

import type { EfactDeclarativeDashboardPayload } from './mapEfactDashboardPayload';
import { DashCard } from './DashCard';
import { SalesByDayChart } from './charts/SalesByDayChart';
import { SalesTrendChart } from './charts/SalesTrendChart';
import { PaymentMethodBars } from './charts/PaymentMethodBars';
import { AttentionStrip } from './sections/AttentionStrip';
import { GettingStarted } from './sections/GettingStarted';
import { KpiRow } from './sections/KpiRow';
import { MiniTable, type MiniTableColumn } from './sections/MiniTable';

import './EfactDashboard.scss';

const PAYMENT_METHOD_BADGES: Record<string, BadgeProps['variant']> = {
  Efectivo: 'success',
  Credito: 'warning',
  'Tarjeta débito': 'info',
  'Tarjeta crédito': 'info',
  Transferencia: 'primary',
  Yape: 'primary',
  Plin: 'primary',
  'Link de pago': 'secondary',
  Depósito: 'secondary',
  'Sin método': 'light',
};

type RecentSaleRow = { time: string; document: string; paymentMethod: string; total: number };

const RECENT_SALES_COLUMNS: Array<MiniTableColumn<RecentSaleRow>> = [
  { key: 'time', label: 'Hora' },
  { key: 'document', label: 'Comprobante' },
  { key: 'paymentMethod', label: 'Método', badge: PAYMENT_METHOD_BADGES },
  { key: 'total', label: 'Total', format: 'currency', align: 'right' },
];

const LOW_STOCK_COLUMNS: Array<
  MiniTableColumn<EfactDeclarativeDashboardPayload['lowStock'][number]>
> = [
  { key: 'name', label: 'Producto' },
  { key: 'currentStock', label: 'Actual', format: 'number', align: 'right' },
  { key: 'minStock', label: 'Mínimo', format: 'number', align: 'right' },
];

const TOP_CUSTOMERS_COLUMNS: Array<
  MiniTableColumn<EfactDeclarativeDashboardPayload['topCustomers'][number]>
> = [
  { key: 'name', label: 'Cliente' },
  { key: 'invoices', label: 'Comprobantes', format: 'number', align: 'right' },
  { key: 'total', label: 'Total', format: 'currency', align: 'right' },
];

const TOP_PRODUCTS_COLUMNS: Array<
  MiniTableColumn<EfactDeclarativeDashboardPayload['topProducts'][number]>
> = [
  { key: 'name', label: 'Producto' },
  { key: 'quantity', label: 'Cant.', format: 'number', align: 'right' },
  { key: 'total', label: 'Total', format: 'currency', align: 'right' },
];

function ViewAll({ to, label }: { to: string; label: string }) {
  return (
    <Link className="efact-dash-card__link" to={to}>
      {label}
    </Link>
  );
}

type EfactDashboardProps = {
  title: string;
  payload: EfactDeclarativeDashboardPayload;
  loading: boolean;
  error: string | null;
  refetch: () => void;
  /** Counts for the "needs attention" strip above the KPIs. */
  attention: { lowStockCount: number; pendingSunatCount: number };
  /** Show the onboarding shortcuts row (tenant setup incomplete). */
  showGettingStarted?: boolean;
  /** Optional AI briefing card, rendered first. */
  briefing?: ReactNode;
};

export function EfactDashboard({
  title,
  payload,
  loading,
  error,
  refetch,
  attention,
  showGettingStarted = false,
  briefing = null,
}: EfactDashboardProps) {
  const { stats, salesByDay, salesByMonth, paymentMethods, topProducts, topCustomers, lowStock } =
    payload;

  const recentSales = useMemo<RecentSaleRow[]>(
    () =>
      payload.recentSales.map((sale) => ({
        time: DateTime.fromISO(sale.issuedAt).setZone('America/Lima').toFormat('HH:mm'),
        document: sale.document,
        paymentMethod: sale.paymentMethod,
        total: sale.total,
      })),
    [payload.recentSales],
  );

  const noData = (len: number) => loading && len === 0;

  return (
    <div className="view-wrapper-scroll efact-dashboard">
      <AppToolbar title={title} onRefresh={refetch}>
        {error ? (
          <div className="efact-dashboard__error" role="alert">
            <i className="ph ph-warning-circle" aria-hidden="true" /> {error}
          </div>
        ) : null}

        <div className="efact-dashboard__sections">
          {briefing ? <div className="efact-dashboard__briefing">{briefing}</div> : null}

          <AttentionStrip
            lowStockCount={attention.lowStockCount}
            pendingSunatCount={attention.pendingSunatCount}
          />

          {showGettingStarted ? <GettingStarted /> : null}

          <KpiRow stats={stats} />

          <div className="efact-dashboard__charts">
            <DashCard
              title="Ventas por día"
              subtitle="Últimos 7 días (neto)"
              isLoading={noData(salesByDay.length)}
              isEmpty={salesByDay.length === 0}
              emptyTitle="Sin ventas en el período"
              emptyIcon="chart-bar"
              bodyHeight={220}
            >
              <SalesByDayChart data={salesByDay} />
            </DashCard>

            <DashCard
              title="Ventas por método de pago"
              subtitle="Distribución del período"
              isLoading={noData(paymentMethods.length)}
              isEmpty={paymentMethods.length === 0}
              emptyTitle="Sin cobros registrados"
              emptyIcon="credit-card"
              bodyHeight={220}
            >
              <PaymentMethodBars data={paymentMethods} />
            </DashCard>
          </div>

          <DashCard
            title="Ventas últimos 12 meses"
            subtitle="Últimos 12 meses (neto)"
            isLoading={noData(salesByMonth.length)}
            isEmpty={salesByMonth.length === 0}
            emptyTitle="Sin histórico de ventas"
            emptyIcon="chart-line"
            bodyHeight={240}
          >
            <SalesTrendChart data={salesByMonth} />
          </DashCard>

          <div className="efact-dashboard__grid">
            <DashCard
              title="Stock crítico"
              subtitle="Por debajo del mínimo"
              action={<ViewAll to="/inventory/reorder" label="Ver reorden" />}
              isEmpty={lowStock.length === 0}
              emptyTitle="Stock en orden"
              emptyDescription="Ningún producto está bajo el mínimo."
              emptyIcon="check-circle"
            >
              <MiniTable columns={LOW_STOCK_COLUMNS} rows={lowStock} />
            </DashCard>

            <DashCard
              title="Top clientes"
              subtitle="Últimos 30 días"
              isEmpty={topCustomers.length === 0}
              emptyTitle="Sin clientes"
              emptyDescription="Aún no hay ventas facturadas a clientes en el período."
              emptyIcon="users"
            >
              <MiniTable columns={TOP_CUSTOMERS_COLUMNS} rows={topCustomers} />
            </DashCard>
          </div>

          <div className="efact-dashboard__grid">
            <DashCard
              title="Ventas recientes"
              subtitle="Hoy"
              action={<ViewAll to="/billing/sales" label="Ver todas" />}
              isEmpty={recentSales.length === 0}
              emptyTitle="Sin ventas hoy"
              emptyDescription="Abre el POS para registrar la primera."
              emptyIcon="receipt"
            >
              <MiniTable columns={RECENT_SALES_COLUMNS} rows={recentSales} />
            </DashCard>

            <DashCard
              title="Top productos"
              subtitle="Últimos 30 días"
              isEmpty={topProducts.length === 0}
              emptyTitle="Sin ventas"
              emptyDescription="Sin productos vendidos en el período."
              emptyIcon="package"
            >
              <MiniTable columns={TOP_PRODUCTS_COLUMNS} rows={topProducts} />
            </DashCard>
          </div>
        </div>
      </AppToolbar>
    </div>
  );
}
