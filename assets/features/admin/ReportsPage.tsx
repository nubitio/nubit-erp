import './ReportsPage.scss';
import {
  BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer,
} from 'recharts';
import { AppToolbar, StatCard } from '@nubit/ui';
import { useDashboardOverview } from './hooks/useDashboardOverview';
import { currencyFormatter } from '@shared/ui/dashboard/formatters';

const CHART_COLORS = [
  'var(--accent-color)',
  '#0e8a8a',
  '#6b4fc8',
  '#c19c00',
  '#c2410c',
  '#2f9e6b',
];

function KpiCard({ label, value, sub }: { label: string; value: string; sub?: string }) {
  return (
    <div className="reports-kpi">
      <div className="reports-kpi__label">{label}</div>
      <div className="reports-kpi__value">{value}</div>
      {sub && <div className="reports-kpi__sub">{sub}</div>}
    </div>
  );
}

function CurrencyTooltip({ active, payload }: { active?: boolean; payload?: { value: number }[] }) {
  if (!active || !payload?.length) return null;
  return <div className="dash-chart-tooltip">{currencyFormatter(payload[0].value)}</div>;
}

function BarRow({ label, pct, color }: { label: string; pct: number; color?: string }) {
  return (
    <div className="reports-bar-row">
      <span className="reports-bar-row__label">{label}</span>
      <div className="reports-bar-wrap">
        <div className="reports-bar" style={{ width: `${pct}%`, background: color ?? 'var(--accent-color)' }} />
      </div>
      <span className="reports-bar-row__pct">{pct}%</span>
    </div>
  );
}

export function ReportsPage() {
  const { data, loading, error, refetch } = useDashboardOverview();
  const { summary, salesByMonth, topProducts, topCustomers, voucherTypes, paymentMethods } = data;

  const ytdRevenue = salesByMonth.length > 0
    ? salesByMonth.reduce((s, m) => s + m.total, 0)
    : summary.salesThisMonth;

  const igvCollected = summary.salesThisMonth * (18 / 118);
  const maxProductRev = topProducts[0]?.total || 1;
  const totalPayments = paymentMethods.reduce((s, p) => s + p.total, 0) || 1;
  const totalVouchers = voucherTypes.reduce((s, v) => s + v.count, 0) || 1;

  return (
    <div className="view-wrapper-scroll reports-page">
      <AppToolbar title="Reports" onRefresh={refetch}>
        {error && <div className="dashboard-error">{error}</div>}

        {/* ── KPI strip ─────────────────────────────────── */}
        <div className="reports-kpi-strip">
          <KpiCard
            label={salesByMonth.length > 1 ? 'YTD revenue' : 'Revenue this month'}
            value={currencyFormatter(ytdRevenue)}
          />
          <KpiCard label="Invoices this month" value={summary.invoicesThisMonth.toLocaleString()} />
          <KpiCard label="Avg. ticket" value={currencyFormatter(summary.avgTicket)} />
          <KpiCard label="IGV collected" value={currencyFormatter(igvCollected)} sub="18% of gross" />
          <KpiCard
            label="Customers"
            value={summary.customersThisMonth.toLocaleString()}
            sub="this month"
          />
        </div>

        {/* ── Charts row ────────────────────────────────── */}
        <div className="reports-charts-row">
          <StatCard
            title="Monthly revenue (12 months)"
            className="chart-card"
            menuVisible={false}
            isLoading={loading}
          >
            <ResponsiveContainer width="100%" height={200}>
              <BarChart data={salesByMonth} margin={{ top: 4, right: 8, left: 8, bottom: 4 }}>
                <CartesianGrid strokeDasharray="3 3" stroke="var(--border-color)" />
                <XAxis dataKey="label" tick={{ fontSize: 11 }} tickLine={false} axisLine={false} />
                <YAxis
                  tickFormatter={(v: number) => currencyFormatter(v)}
                  tick={{ fontSize: 11 }}
                  tickLine={false}
                  axisLine={false}
                  width={80}
                />
                <Tooltip content={<CurrencyTooltip />} />
                <Bar dataKey="total" fill="var(--accent-color)" radius={[3, 3, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>
          </StatCard>

          <StatCard title="Document & payment mix" menuVisible={false} isLoading={loading}>
            {voucherTypes.length > 0 && (
              <>
                <div className="reports-mix-heading">By document type</div>
                <div className="reports-bar-list">
                  {voucherTypes.map((v, i) => (
                    <BarRow
                      key={v.type}
                      label={v.type}
                      pct={Math.round((v.count / totalVouchers) * 100)}
                      color={CHART_COLORS[i % CHART_COLORS.length]}
                    />
                  ))}
                </div>
              </>
            )}
            {paymentMethods.length > 0 && (
              <>
                <div className="reports-mix-heading reports-mix-heading--spaced">Payment methods</div>
                <div className="reports-bar-list">
                  {paymentMethods.map((p) => (
                    <BarRow
                      key={p.method}
                      label={p.method}
                      pct={Math.round((p.total / totalPayments) * 100)}
                    />
                  ))}
                </div>
              </>
            )}
          </StatCard>
        </div>

        {/* ── Rankings row ──────────────────────────────── */}
        <div className="reports-rankings-row">
          <StatCard title="Top clients" menuVisible={false} isLoading={loading}>
            {topCustomers.length === 0 ? (
              <p className="reports-empty">No hay datos de clientes para el período.</p>
            ) : (
              <table className="reports-table">
                <thead>
                  <tr>
                    <th>Client</th>
                    <th className="reports-table__r">Invoices</th>
                    <th className="reports-table__r">Revenue</th>
                  </tr>
                </thead>
                <tbody>
                  {topCustomers.slice(0, 8).map((c, i) => (
                    <tr key={i}>
                      <td>
                        <div className="reports-client-cell">
                          <div
                            className="reports-avatar"
                            style={{ background: CHART_COLORS[i % CHART_COLORS.length] }}
                          >
                            {c.customer
                              .split(' ')
                              .map((w) => w[0])
                              .slice(0, 2)
                              .join('')
                              .toUpperCase()}
                          </div>
                          <span>{c.customer}</span>
                        </div>
                      </td>
                      <td className="reports-table__r reports-table__num">{c.invoices}</td>
                      <td className="reports-table__r reports-table__num reports-table__bold">
                        {currencyFormatter(c.total)}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </StatCard>

          <StatCard title="Top products by revenue" menuVisible={false} isLoading={loading}>
            {topProducts.length === 0 ? (
              <p className="reports-empty">No hay datos de productos para el período.</p>
            ) : (
              <div className="reports-products-list">
                {topProducts.slice(0, 8).map((p, i) => (
                  <div key={i} className="reports-product-row">
                    <div className="reports-product-row__header">
                      <span className="reports-product-row__name">{p.product}</span>
                      <span className="reports-product-row__rev">{currencyFormatter(p.total)}</span>
                    </div>
                    <div className="reports-bar-wrap">
                      <div
                        className="reports-bar"
                        style={{
                          width: `${Math.round((p.total / maxProductRev) * 100)}%`,
                          background: CHART_COLORS[i % CHART_COLORS.length],
                        }}
                      />
                    </div>
                    <span className="reports-product-row__qty">
                      {p.quantity.toLocaleString()} sold
                    </span>
                  </div>
                ))}
              </div>
            )}
          </StatCard>
        </div>
      </AppToolbar>
    </div>
  );
}
