import { useNavigate } from 'react-router-dom';
import { EmptyState, StatCard } from '@nubit/ui';
import { CashRegisterStatus, LowStockProduct } from '../types';
import { currencyFormatter, decimalFormatter } from '@shared/ui/dashboard/formatters';

function SectionHeader({ title, subtitle }: { title: string; subtitle?: string }) {
  return (
    <div className="dash-section-header">
      <span className="dash-section-header__title">{title}</span>
      {subtitle && <span className="dash-section-header__sub">{subtitle}</span>}
    </div>
  );
}

type Props = {
  cashRegisters: CashRegisterStatus[];
  cashboxLoading: boolean;
  lowStockProducts: LowStockProduct[];
};

export function DashboardOperations({ cashRegisters, cashboxLoading, lowStockProducts }: Props) {
  const navigate = useNavigate();

  const goToKardex = (productId: string | null) => {
    const params = new URLSearchParams();
    if (productId) params.set('product', productId);
    navigate(`/kardex?${params.toString()}`);
  };

  const goToReceipt = () => navigate('/receipt');
  return (
    <section className="dash-ops-section">
      <SectionHeader title="Estado operacional" />
      <div className="dash-ops-grid">
        <StatCard
          title="Cajas"
          className=" list-card"
          menuVisible={false}
          isLoading={cashboxLoading}
        >
          <ul className="ranked-list">
            {cashRegisters.map((item) => (
              <li key={item.id}>
                <div className="ranked-list__title">{item.name}</div>
                <div className="ranked-list__meta">
                  <span className={item.status === 'open' ? 'cash-open' : 'cash-closed'}>
                    <span className={`cash-dot ${item.status === 'open' ? 'open' : ''}`} />
                    {item.status === 'open' ? 'Abierta' : 'Cerrada'}
                    {item.cashier ? ` · ${item.cashier}` : ''}
                  </span>
                  <span>{currencyFormatter(item.balance ?? 0)}</span>
                </div>
              </li>
            ))}
            {cashRegisters.length === 0 && !cashboxLoading && (
              <li className="dash-list-empty">
                <EmptyState
                  size="sm"
                  icon="cash-register"
                  title="Sin cajas registradas"
                  description="Configura al menos una caja para operar el punto de venta."
                />
              </li>
            )}
          </ul>
        </StatCard>

        <StatCard title="Stock crítico" className=" list-card" menuVisible={false}>
          <ul className="ranked-list stock-alert">
            {lowStockProducts.map((item, idx) => (
              <li
                key={idx}
                className={
                  item.stockPercentage < 30
                    ? 'critical'
                    : item.stockPercentage < 60
                      ? 'warning'
                      : ''
                }
                style={{ cursor: 'pointer' }}
                onClick={() => goToKardex(item.productId)}
                title="Ver Kardex"
              >
                <div className="ranked-list__title">{item.product}</div>
                <div className="ranked-list__meta">
                  <span className="stock-info">
                    {decimalFormatter(item.currentStock)} / {decimalFormatter(item.minStock)} u.
                    <span className="stock-badge">{item.stockPercentage}%</span>
                  </span>
                  <span
                    role="button"
                    title="Crear entrada"
                    onClick={(e) => { e.stopPropagation(); goToReceipt(); }}
                    style={{ fontSize: 16, marginLeft: 4, color: 'var(--accent-color, #3b82f6)' }}
                  >
                    <i className="ph ph-plus-circle" />
                  </span>
                </div>
              </li>
            ))}
            {lowStockProducts.length === 0 && (
              <li className="dash-list-empty">
                <EmptyState
                  size="sm"
                  variant="success"
                  icon="check-circle"
                  title="Stock en niveles óptimos"
                  description="Ningún producto está por debajo del mínimo configurado."
                />
              </li>
            )}
          </ul>
        </StatCard>
      </div>
    </section>
  );
}
