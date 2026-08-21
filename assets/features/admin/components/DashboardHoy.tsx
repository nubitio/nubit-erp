import { useNavigate } from 'react-router-dom';
import { formatMoney } from '@shared/lib/displayFormat';
import './DashboardHoy.scss';

export type DashboardHoyStats = {
  lowStockCount: number;
  receivablesCount: number;
  receivablesTotal: number;
  payablesCount: number;
  payablesTotal: number;
  pendingSunatCount: number;
};

type Props = {
  stats: DashboardHoyStats;
};

export function DashboardHoy({ stats }: Props) {
  const navigate = useNavigate();
  const items = [
    {
      key: 'stock',
      count: stats.lowStockCount,
      label: stats.lowStockCount === 1 ? 'producto bajo mínimo' : 'productos bajo mínimo',
      hint: 'Reponer',
      to: '/inventory/reorder',
      tone: stats.lowStockCount > 0 ? 'warning' : 'ok',
    },
    {
      key: 'ar',
      count: stats.receivablesCount,
      label: stats.receivablesCount === 1 ? 'cuenta por cobrar' : 'cuentas por cobrar',
      hint: formatMoney(stats.receivablesTotal),
      to: '/accounts-receivable',
      tone: stats.receivablesCount > 0 ? 'warning' : 'ok',
    },
    {
      key: 'ap',
      count: stats.payablesCount,
      label: stats.payablesCount === 1 ? 'cuenta por pagar' : 'cuentas por pagar',
      hint: formatMoney(stats.payablesTotal),
      to: '/accounts-payable',
      tone: stats.payablesCount > 0 ? 'accent' : 'ok',
    },
    {
      key: 'sunat',
      count: stats.pendingSunatCount,
      label: stats.pendingSunatCount === 1 ? 'comprobante pendiente SUNAT' : 'comprobantes pendientes SUNAT',
      hint: 'Facturación',
      to: '/billing/documents',
      tone: stats.pendingSunatCount > 0 ? 'danger' : 'ok',
    },
  ];

  return (
    <section className="dash-hoy" aria-label="Qué hacer hoy">
      <div className="dash-hoy__header">
        <h2 className="dash-hoy__title">Hoy</h2>
        <p className="dash-hoy__subtitle">Lo que necesita tu atención en este local.</p>
      </div>
      <div className="dash-hoy__grid">
        {items.map((item) => (
          <button
            key={item.key}
            type="button"
            className={`dash-hoy__card dash-hoy__card--${item.tone}`}
            onClick={() => navigate(item.to)}
          >
            <strong className="dash-hoy__count">{item.count}</strong>
            <span className="dash-hoy__label">{item.label}</span>
            <span className="dash-hoy__hint">{item.hint}</span>
          </button>
        ))}
      </div>
      <div className="dash-hoy__checklist">
        <button type="button" onClick={() => navigate('/config/company')}>
          Empresa
        </button>
        <button type="button" onClick={() => navigate('/config/series')}>
          Series SUNAT
        </button>
        <button type="button" onClick={() => navigate('/catalog/products')}>
          Primer producto
        </button>
        <button type="button" onClick={() => navigate('/pos')}>
          Primera venta
        </button>
        <button type="button" onClick={() => navigate('/setup/import')}>
          Importar Excel
        </button>
      </div>
    </section>
  );
}
