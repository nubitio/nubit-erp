import './FinancialReportsPage.scss';
import { useState } from 'react';
import { Badge } from '@nubitio/ui';
import {
  Bar,
  BarChart,
  CartesianGrid,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';
import { financialReportsApi } from './api/financialReportsEndpoints';
import { useCashFlow, useProfitAndLoss } from './useFinancialReports';

type TabKey = 'pnl' | 'cashflow';

const TABS: { key: TabKey; label: string }[] = [
  { key: 'pnl', label: 'Estado de resultados' },
  { key: 'cashflow', label: 'Flujo de caja' },
];

function fmtMoney(amount: number): string {
  return `S/ ${amount.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

interface KpiCardProps {
  label: string;
  value: string;
  icon: string;
  tone?: 'danger' | 'success' | 'default';
  loading?: boolean;
  badge?: string;
}

function KpiCard({ label, value, icon, tone = 'default', loading, badge }: KpiCardProps) {
  return (
    <div className={`fr-kpi fr-kpi--${tone}`}>
      <div className="fr-kpi__header">
        <span className="fr-kpi__label">{label}</span>
        <i className={`ph ${icon} fr-kpi__icon`} aria-hidden="true" />
      </div>
      <span className="fr-kpi__value">{loading ? '—' : value}</span>
      {badge && (
        <Badge variant="warning" size="sm" outlined>
          {badge}
        </Badge>
      )}
    </div>
  );
}

function MoneyTooltip({ active, payload, label }: { active?: boolean; payload?: { value: number; name: string }[]; label?: string }) {
  if (!active || !payload?.length) return null;
  return (
    <div className="fr-chart-tooltip">
      <strong>{label}</strong>
      {payload.map((p) => (
        <div key={p.name}>
          {p.name}: {fmtMoney(p.value)}
        </div>
      ))}
    </div>
  );
}

function ProfitAndLossTab() {
  const { data, isLoading } = useProfitAndLoss();

  const historial = [...(data?.historial ?? [])].reverse();

  return (
    <div className="fr-tab-content">
      <div className="fr-card">
        <div className="fr-card__title">Últimos 6 meses</div>
        {isLoading ? (
          <div className="fr-loading">Cargando…</div>
        ) : (
          <ResponsiveContainer width="100%" height={220}>
            <BarChart data={historial} margin={{ top: 4, right: 8, left: 8, bottom: 4 }}>
              <CartesianGrid strokeDasharray="3 3" stroke="var(--border-color)" />
              <XAxis dataKey="label" tick={{ fontSize: 11 }} tickLine={false} axisLine={false} />
              <YAxis
                tickFormatter={(v: number) => fmtMoney(v)}
                tick={{ fontSize: 11 }}
                tickLine={false}
                axisLine={false}
                width={90}
              />
              <Tooltip content={<MoneyTooltip />} />
              <Bar dataKey="ingresos" name="Ingresos" fill="var(--success-color, #16a34a)" radius={[3, 3, 0, 0]} />
              <Bar dataKey="costoVentas" name="Costo de ventas" fill="var(--warning-color, #d97706)" radius={[3, 3, 0, 0]} />
              <Bar dataKey="utilidadNeta" name="Utilidad neta" fill="var(--accent-color, #4f46e5)" radius={[3, 3, 0, 0]} />
            </BarChart>
          </ResponsiveContainer>
        )}
      </div>

      <div className="fr-card">
        <div className="fr-card__title">Gastos operativos por categoría</div>
        {isLoading ? (
          <div className="fr-loading">Cargando…</div>
        ) : (
          <table className="fr-table">
            <thead>
              <tr>
                <th>Categoría</th>
                <th>Monto</th>
              </tr>
            </thead>
            <tbody>
              {(data?.gastosOperativos.porCategoria.length ?? 0) === 0 ? (
                <tr>
                  <td colSpan={2} className="fr-table__empty">Sin gastos registrados en este periodo.</td>
                </tr>
              ) : (
                data?.gastosOperativos.porCategoria.map((gasto) => (
                  <tr key={gasto.categoria}>
                    <td>{gasto.categoria}</td>
                    <td className="fr-table__amount">{fmtMoney(gasto.monto)}</td>
                  </tr>
                ))
              )}
            </tbody>
            {(data?.gastosOperativos.porCategoria.length ?? 0) > 0 && (
              <tfoot>
                <tr>
                  <td>Total</td>
                  <td className="fr-table__amount">{fmtMoney(data?.gastosOperativos.total ?? 0)}</td>
                </tr>
              </tfoot>
            )}
          </table>
        )}
      </div>
    </div>
  );
}

function CashFlowTab() {
  const { data, isLoading } = useCashFlow();

  return (
    <div className="fr-tab-content">
      <div className="fr-card">
        <div className="fr-card__title">Flujo de caja real</div>
        {isLoading ? (
          <div className="fr-loading">Cargando…</div>
        ) : (data?.real.length ?? 0) === 0 ? (
          <p className="fr-empty">No hay movimientos de caja en el periodo mostrado.</p>
        ) : (
          <ResponsiveContainer width="100%" height={220}>
            <BarChart data={data?.real} margin={{ top: 4, right: 8, left: 8, bottom: 4 }}>
              <CartesianGrid strokeDasharray="3 3" stroke="var(--border-color)" />
              <XAxis dataKey="weekKey" tick={{ fontSize: 11 }} tickLine={false} axisLine={false} />
              <YAxis
                tickFormatter={(v: number) => fmtMoney(v)}
                tick={{ fontSize: 11 }}
                tickLine={false}
                axisLine={false}
                width={90}
              />
              <Tooltip content={<MoneyTooltip />} />
              <Bar dataKey="entradas" name="Entradas" fill="var(--success-color, #16a34a)" radius={[3, 3, 0, 0]} />
              <Bar dataKey="salidas" name="Salidas" fill="var(--danger-color, #dc2626)" radius={[3, 3, 0, 0]} />
            </BarChart>
          </ResponsiveContainer>
        )}
      </div>

      <div className="fr-card">
        <div className="fr-card__title">Proyección — próximos 30 días</div>
        {isLoading ? (
          <div className="fr-loading">Cargando…</div>
        ) : (
          <table className="fr-table">
            <thead>
              <tr>
                <th>Semana</th>
                <th>Cobros esperados</th>
                <th>Pagos esperados</th>
                <th>Neto esperado</th>
              </tr>
            </thead>
            <tbody>
              {(data?.proyectado.length ?? 0) === 0 ? (
                <tr>
                  <td colSpan={4} className="fr-table__empty">Sin cuentas por cobrar/pagar próximas a vencer.</td>
                </tr>
              ) : (
                data?.proyectado.map((week) => (
                  <tr key={week.weekKey}>
                    <td>{week.weekKey}</td>
                    <td className="fr-table__amount">{fmtMoney(week.cobrosEsperados)}</td>
                    <td className="fr-table__amount">{fmtMoney(week.pagosEsperados)}</td>
                    <td className="fr-table__amount">{fmtMoney(week.netoEsperado)}</td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
}

export function FinancialReportsPage() {
  const [activeTab, setActiveTab] = useState<TabKey>('pnl');
  const { data: pnlData, isLoading: pnlLoading } = useProfitAndLoss();

  const handleExport = () => {
    window.open(financialReportsApi.exportUrl(pnlData?.period), '_blank', 'noopener');
  };

  return (
    <div className="view-wrapper-scroll">
      <div className="fr-page">
        <div className="fr-page__header">
          <div>
            <h1 className="fr-page__title">Reportes financieros</h1>
            <p className="fr-page__subtitle">
              {pnlLoading ? '…' : pnlData?.periodLabel} · Estado de resultados y flujo de caja.
            </p>
          </div>
          <button type="button" className="fr-export-button" onClick={handleExport}>
            <i className="ph ph-download-simple" aria-hidden="true" />
            Exportar a Excel
          </button>
        </div>

        <div className="fr-kpi-grid">
          <KpiCard
            label="INGRESOS NETOS"
            value={fmtMoney(pnlData?.ingresos.neto ?? 0)}
            icon="ph-trend-up"
            tone="success"
            loading={pnlLoading}
          />
          <KpiCard
            label="COSTO DE VENTAS"
            value={fmtMoney(pnlData?.costoVentas.monto ?? 0)}
            icon="ph-tag"
            loading={pnlLoading}
            badge="Estimado"
          />
          <KpiCard
            label="GASTOS OPERATIVOS"
            value={fmtMoney(pnlData?.gastosOperativos.total ?? 0)}
            icon="ph-receipt"
            loading={pnlLoading}
          />
          <KpiCard
            label="UTILIDAD NETA"
            value={fmtMoney(pnlData?.utilidadNeta ?? 0)}
            icon="ph-percent"
            tone={pnlData && pnlData.utilidadNeta < 0 ? 'danger' : 'success'}
            loading={pnlLoading}
          />
        </div>

        <div className="fr-tabs" role="tablist" aria-label="Secciones de reportes financieros">
          {TABS.map((tab) => (
            <button
              key={tab.key}
              type="button"
              role="tab"
              aria-selected={activeTab === tab.key}
              className={`fr-tab${activeTab === tab.key ? ' fr-tab--active' : ''}`}
              onClick={() => setActiveTab(tab.key)}
            >
              {tab.label}
            </button>
          ))}
        </div>

        {activeTab === 'pnl' && <ProfitAndLossTab />}
        {activeTab === 'cashflow' && <CashFlowTab />}
      </div>
    </div>
  );
}
