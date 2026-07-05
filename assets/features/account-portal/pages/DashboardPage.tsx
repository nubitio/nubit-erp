import { useEffect } from 'react';
import { Link, Navigate, useSearchParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { AccountPortalLayout } from '../components/AccountPortalLayout';
import { ProvisionAlerts } from '../components/ProvisionAlerts';
import { TenantCard } from '../components/TenantCard';
import { accountPortalClient } from '../api/accountPortalClient';
import { useAccountSession } from '../hooks/useAccountSession';
import { useApToast } from '../providers/ApToastProvider';

export function DashboardPage() {
  const session = useAccountSession();
  const queryClient = useQueryClient();
  const { notify } = useApToast();
  const [searchParams] = useSearchParams();

  const dashboard = useQuery({
    queryKey: ['account-dashboard'],
    queryFn: accountPortalClient.getDashboard,
    enabled: session.data?.authenticated === true,
    refetchInterval: (query) => {
      const provisions = query.state.data?.provisions ?? [];
      const hasActive = provisions.some((item) => item.status === 'dispatched' || item.status === 'running');
      return hasActive ? 4000 : false;
    },
  });

  useEffect(() => {
    const payment = searchParams.get('payment');
    if (payment === 'cancelled') {
      notify('El pago fue cancelado. Puedes intentar nuevamente.', 'warning');
    }
  }, [notify, searchParams]);

  if (session.isLoading) return null;
  if (!session.data?.authenticated) {
    return <Navigate to="/account/login" replace />;
  }

  const tenants = dashboard.data?.tenants ?? [];
  const provisions = dashboard.data?.provisions ?? [];
  const activeProvisions = provisions.filter((item) => item.status === 'dispatched' || item.status === 'running');
  const failedProvisions = provisions.filter((item) => item.status === 'failed');

  const dismissProvision = async (provisionId: number) => {
    await accountPortalClient.dismissProvision(provisionId);
    await queryClient.invalidateQueries({ queryKey: ['account-dashboard'] });
  };

  return (
    <AccountPortalLayout
      variant="app"
      wide
      showLogout
      title="Mis empresas"
      subtitle={dashboard.data?.account.email}
      headerAction={
        <Link to="/register/new-tenant" className="ap-cta">
          <i className="ph ph-plus" aria-hidden="true" />
          Nueva empresa
        </Link>
      }
    >
      <div className="ap-dashboard-stats">
        <div className="ap-stat">
          <strong>{tenants.length}</strong>
          <span>Empresas activas</span>
        </div>
        <div className="ap-stat">
          <strong>{activeProvisions.length}</strong>
          <span>En creación</span>
        </div>
      </div>

      <ProvisionAlerts
        active={activeProvisions}
        failed={failedProvisions}
        onDismiss={dismissProvision}
      />

      {tenants.length === 0 ? (
        <div className="ap-empty">
          <i className="ph ph-buildings" aria-hidden="true" />
          <h3>Aún no tienes empresas</h3>
          <p>Crea tu primera instancia de eFact y empieza a facturar en minutos.</p>
          <Link to="/register/new-tenant" className="ap-cta">
            Crear mi primera empresa
          </Link>
        </div>
      ) : (
        <div className="ap-tenant-grid">
          {tenants.map((tenant) => (
            <TenantCard key={tenant.id} tenant={tenant} />
          ))}
        </div>
      )}
    </AccountPortalLayout>
  );
}