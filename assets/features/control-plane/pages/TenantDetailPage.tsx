import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Link, useParams, useSearchParams } from 'react-router-dom';
import { Button } from '@nubitio/ui';
import { controlPlaneClient } from '../api/controlPlaneClient';
import { CpAlert, CpHubTabs, CpPage, CpPageHeader, CpPageSkeleton } from '../components/layout';
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
import { useCpConfirm } from '../hooks/useCpConfirm';
import { CP_STRINGS } from '../i18n/es';
import type { TenantTab } from '../types';

const TABS: Array<{ id: TenantTab; label: string; icon: string }> = [
  { id: 'general', label: CP_STRINGS.tabs.general, icon: 'pencil-simple' },
  { id: 'domains', label: CP_STRINGS.tabs.domains, icon: 'globe' },
  { id: 'provisioning', label: CP_STRINGS.tabs.provisioning, icon: 'rocket-launch' },
  { id: 'usage', label: CP_STRINGS.tabs.usage, icon: 'chart-line-up' },
  { id: 'subscription', label: CP_STRINGS.tabs.subscription, icon: 'credit-card' },
  { id: 'backups', label: CP_STRINGS.tabs.backups, icon: 'archive' },
  { id: 'certificates', label: CP_STRINGS.tabs.certificates, icon: 'shield-check' },
  { id: 'notifications', label: CP_STRINGS.tabs.notifications, icon: 'bell' },
  { id: 'features', label: CP_STRINGS.tabs.features, icon: 'toggle-right' },
];

const isTenantTab = (value: string | null): value is TenantTab =>
  TABS.some((tab) => tab.id === value);

export const TenantDetailPage = () => {
  const { id } = useParams<{ id: string }>();
  const tenantId = Number(id);
  const [searchParams, setSearchParams] = useSearchParams();
  const queryClient = useQueryClient();
  const { isAdmin, isSuperAdmin } = useControlPlaneRoles();
  const { ask, ConfirmHost } = useCpConfirm();

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
    return (
      <CpPage>
        <CpAlert tone="danger">ID de tenant inválido.</CpAlert>
      </CpPage>
    );
  }

  if (detail.isLoading) {
    return <CpPageSkeleton variant="detail" />;
  }

  if (detail.error || !detail.data) {
    return (
      <CpPage>
        <CpAlert tone="danger">
          {(detail.error as Error)?.message ?? CP_STRINGS.tenantNotFound}
        </CpAlert>
        <Link to="/tenants">
          <Button variant="ghost" icon="ph-arrow-left">
            Volver a tenants
          </Button>
        </Link>
      </CpPage>
    );
  }

  const { tenant } = detail.data;
  const quotaAlertCount = detail.data.usage.quotaAlerts.length;

  const setTab = (tab: TenantTab) => {
    setSearchParams(tab === 'general' ? {} : { tab });
  };

  const hubTabs = TABS.map((tab) => ({
    id: tab.id,
    label: tab.label,
    icon: tab.icon,
    badge:
      tab.id === 'domains'
        ? detail.data.domains.length
        : tab.id === 'usage'
          ? quotaAlertCount
          : tab.id === 'backups'
            ? detail.data.backups.length
            : undefined,
    badgeVariant: tab.id === 'usage' && quotaAlertCount > 0 ? ('warning' as const) : undefined,
  }));

  return (
    <CpPage fill wide className="cp-tenant-hub">
      <div className="nb-feature-hub">
        <header className="nb-feature-hub__header">
          <CpPageHeader
            title={tenant.name}
            subtitle={CP_STRINGS.tenantDetail.subtitle}
            breadcrumb={{ label: CP_STRINGS.nav.tenants, to: '/tenants' }}
            actions={
              <>
                <TenantStatusBadge status={tenant.status} />
                {isAdmin &&
                  (tenant.status === 'suspended' ? (
                    <Button
                      variant="ghost"
                      size="sm"
                      loading={unsuspend.isPending}
                      onClick={() => unsuspend.mutate()}
                    >
                      {CP_STRINGS.tenants.unsuspend}
                    </Button>
                  ) : (
                    <Button
                      variant="ghost"
                      size="sm"
                      loading={suspend.isPending}
                      onClick={() =>
                        ask({
                          title: CP_STRINGS.tenantDetail.suspendConfirmTitle,
                          message: CP_STRINGS.tenantDetail.suspendConfirm(tenant.name),
                          variant: 'warning',
                          onConfirm: () => suspend.mutate(),
                        })
                      }
                    >
                      {CP_STRINGS.tenants.suspend}
                    </Button>
                  ))}
              </>
            }
          />
          <CpHubTabs
            tabs={hubTabs}
            active={activeTab as TenantTab}
            onChange={setTab}
            ariaLabel={CP_STRINGS.tenantDetail.tabsAria}
          />
        </header>

        <div className="nb-feature-hub__content">
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
      <ConfirmHost />
    </CpPage>
  );
};