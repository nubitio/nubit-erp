import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Link, useParams, useSearchParams } from 'react-router-dom';
import { controlPlaneClient } from '../api/controlPlaneClient';
import { TenantStatusBadge } from '../components/TenantStatusBadge';
import { BackupsTab } from '../components/tenant/BackupsTab';
import { CertificatesTab } from '../components/tenant/CertificatesTab';
import { DomainsTab } from '../components/tenant/DomainsTab';
import { GeneralTab } from '../components/tenant/GeneralTab';
import { ProvisioningTab } from '../components/tenant/ProvisioningTab';
import { SubscriptionTab } from '../components/tenant/SubscriptionTab';
import { FeaturesTab } from '../components/tenant/FeaturesTab';
import { NotificationsTab } from '../components/tenant/NotificationsTab';
import { UsageTab } from '../components/tenant/UsageTab';
import { useControlPlaneRoles } from '../hooks/useControlPlaneRoles';
import { CP_STRINGS } from '../i18n/es';
import type { TenantTab } from '../types';

const TABS: Array<{ id: TenantTab; label: string; icon: string }> = [
  { id: 'general', label: CP_STRINGS.tabs.general, icon: 'bi-pencil-square' },
  { id: 'domains', label: CP_STRINGS.tabs.domains, icon: 'bi-globe' },
  { id: 'provisioning', label: CP_STRINGS.tabs.provisioning, icon: 'bi-rocket' },
  { id: 'usage', label: CP_STRINGS.tabs.usage, icon: 'bi-graph-up' },
  { id: 'subscription', label: CP_STRINGS.tabs.subscription, icon: 'bi-credit-card' },
  { id: 'backups', label: CP_STRINGS.tabs.backups, icon: 'bi-archive' },
  { id: 'certificates', label: CP_STRINGS.tabs.certificates, icon: 'bi-shield-lock' },
  { id: 'notifications', label: CP_STRINGS.tabs.notifications, icon: 'bi-bell' },
  { id: 'features', label: CP_STRINGS.tabs.features, icon: 'bi-toggles' },
];

const isTenantTab = (value: string | null): value is TenantTab =>
  TABS.some((tab) => tab.id === value);

export const TenantDetailPage = () => {
  const { id } = useParams<{ id: string }>();
  const tenantId = Number(id);
  const [searchParams, setSearchParams] = useSearchParams();
  const queryClient = useQueryClient();
  const { isAdmin, isSuperAdmin } = useControlPlaneRoles();

  const activeTab = isTenantTab(searchParams.get('tab')) ? searchParams.get('tab')! : 'general';
  const certificateParam = Number(searchParams.get('certificate'));
  const initialCertificateId =
    Number.isFinite(certificateParam) && certificateParam > 0 ? certificateParam : null;

  const detail = useQuery({
    queryKey: ['control-plane', 'tenant', tenantId],
    queryFn: () => controlPlaneClient.getTenantDetail(tenantId),
    enabled: Number.isFinite(tenantId) && tenantId > 0,
  });

  const suspend = useMutation({
    mutationFn: () => controlPlaneClient.suspendTenant(tenantId),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['control-plane', 'tenant', tenantId] }),
  });

  const unsuspend = useMutation({
    mutationFn: () => controlPlaneClient.unsuspendTenant(tenantId),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['control-plane', 'tenant', tenantId] }),
  });

  if (!Number.isFinite(tenantId) || tenantId <= 0) {
    return <div className="cp-page cp-alert cp-alert--danger">Invalid tenant ID.</div>;
  }

  if (detail.isLoading) {
    return <div className="cp-page cp-page--loading">Cargando tenant…</div>;
  }

  if (detail.error || !detail.data) {
    return (
      <div className="cp-page">
        <div className="cp-alert cp-alert--danger">
          {(detail.error as Error)?.message ?? CP_STRINGS.tenantNotFound}
        </div>
        <Link to="/tenants" className="cp-btn cp-btn--ghost">
          ← Volver a tenants
        </Link>
      </div>
    );
  }

  const { tenant } = detail.data;
  const quotaAlertCount = detail.data.usage.quotaAlerts.length;

  const setTab = (tab: TenantTab) => {
    setSearchParams(tab === 'general' ? {} : { tab });
  };

  return (
    <div className="cp-page">
      <header className="cp-page__header cp-page__header--split">
        <div>
          <Link to="/tenants" className="cp-breadcrumb">
            ← Tenants
          </Link>
          <h1>{tenant.name}</h1>
          <p>Gestión operativa del tenant.</p>
        </div>
        <div className="cp-page__actions">
          <TenantStatusBadge status={tenant.status} />
          {isAdmin &&
            (tenant.status === 'suspended' ? (
              <button
                type="button"
                className="cp-btn cp-btn--ghost"
                disabled={unsuspend.isPending}
                onClick={() => unsuspend.mutate()}
              >
                Reactivar
              </button>
            ) : (
              <button
                type="button"
                className="cp-btn cp-btn--ghost"
                disabled={suspend.isPending}
                onClick={() => {
                  if (window.confirm(`¿Suspender tenant ${tenant.name}?`)) suspend.mutate();
                }}
              >
                Suspender
              </button>
            ))}
        </div>
      </header>

      <nav className="cp-tabs" aria-label="Tenant sections">
        {TABS.map((tab) => (
          <button
            key={tab.id}
            type="button"
            className={`cp-tabs__item${activeTab === tab.id ? ' cp-tabs__item--active' : ''}`}
            onClick={() => setTab(tab.id)}
          >
            <i className={`bi ${tab.icon}`} />
            {tab.label}
            {tab.id === 'domains' && detail.data.domains.length > 0 && (
              <span className="cp-tabs__badge">{detail.data.domains.length}</span>
            )}
            {tab.id === 'usage' && quotaAlertCount > 0 && (
              <span className="cp-tabs__badge cp-tabs__badge--warn">{quotaAlertCount}</span>
            )}
            {tab.id === 'backups' && detail.data.backups.length > 0 && (
              <span className="cp-tabs__badge">{detail.data.backups.length}</span>
            )}
          </button>
        ))}
      </nav>

      <div className="cp-tab-panel">
        {activeTab === 'general' && (
          <GeneralTab
            tenant={detail.data.tenant}
            plans={detail.data.plans}
            isAdmin={isAdmin}
            onUpdated={() => detail.refetch()}
          />
        )}
        {activeTab === 'domains' && (
          <DomainsTab
            tenantId={tenantId}
            domains={detail.data.domains}
            isAdmin={isAdmin}
            onUpdated={() => detail.refetch()}
          />
        )}
        {activeTab === 'provisioning' && (
          <ProvisioningTab
            tenantId={tenantId}
            primaryDomain={detail.data.primaryDomain}
            provisions={detail.data.provisions}
            isAdmin={isAdmin}
            onUpdated={() => detail.refetch()}
          />
        )}
        {activeTab === 'usage' && (
          <UsageTab
            quotaSummary={detail.data.usage.quotaSummary}
            usageLatest={detail.data.usage.usageLatest}
            quotaAlerts={detail.data.usage.quotaAlerts}
          />
        )}
        {activeTab === 'subscription' && (
          <SubscriptionTab
            subscription={detail.data.subscription}
            paymentEvents={detail.data.paymentEvents}
          />
        )}
        {activeTab === 'backups' && (
          <BackupsTab
            tenantId={tenantId}
            tenant={detail.data.tenant}
            backups={detail.data.backups}
            binlogSummary={detail.data.binlogSummary}
            isAdmin={isAdmin}
            isSuperAdmin={isSuperAdmin}
            onUpdated={() => detail.refetch()}
          />
        )}
        {activeTab === 'certificates' && (
          <CertificatesTab
            tenantId={tenantId}
            certificates={detail.data.certificates}
            isAdmin={isAdmin}
            onUpdated={() => detail.refetch()}
            initialCertificateId={initialCertificateId}
            onCertificateModalClose={() => {
              const next = new URLSearchParams(searchParams);
              next.delete('certificate');
              setSearchParams(next, { replace: true });
            }}
          />
        )}
        {activeTab === 'notifications' && (
          <NotificationsTab tenantId={tenantId} isAdmin={isAdmin} />
        )}
        {activeTab === 'features' && (
          <FeaturesTab tenantId={tenantId} isAdmin={isAdmin} />
        )}
      </div>
    </div>
  );
};