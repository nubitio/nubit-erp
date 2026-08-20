import { useEffect, useMemo, useState } from 'react';
import { Navigate, useParams, useSearchParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Button } from '@nubitio/ui';
import { AccountPortalLayout } from '../components/AccountPortalLayout';
import { PlanSelector } from '../components/PlanSelector';
import { StatusBadge } from '../components/StatusBadge';
import { accountPortalClient } from '../api/accountPortalClient';
import { useAccountSession } from '../hooks/useAccountSession';
import { useApToast } from '../providers/ApToastProvider';
import type { BillingCycle, PaymentGateway, PlanKey } from '../lib/plans';

export function TenantDetailPage() {
  const { tenantId = '' } = useParams();
  const id = Number(tenantId);
  const session = useAccountSession();
  const queryClient = useQueryClient();
  const { notify } = useApToast();
  const [searchParams] = useSearchParams();

  const detail = useQuery({
    queryKey: ['account-tenant', id],
    queryFn: () => accountPortalClient.getTenant(id),
    enabled: Number.isFinite(id) && id > 0 && session.data?.authenticated === true,
  });

  const currentPlan = (detail.data?.tenant.plan ?? 'free') as PlanKey;
  const [selectedPlan, setSelectedPlan] = useState<PlanKey>(currentPlan);
  const [gateway, setGateway] = useState<PaymentGateway>(
    (detail.data?.subscription?.gateway as PaymentGateway) ?? 'mercadopago',
  );
  const [billingCycle, setBillingCycle] = useState<BillingCycle>(
    detail.data?.subscription?.billing_cycle === 'annual' ? 'annual' : 'monthly',
  );
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    setSelectedPlan((detail.data?.tenant.plan ?? 'free') as PlanKey);
    if (detail.data?.subscription?.gateway) {
      setGateway(detail.data.subscription.gateway as PaymentGateway);
    }
    setBillingCycle(detail.data?.subscription?.billing_cycle === 'annual' ? 'annual' : 'monthly');
  }, [detail.data]);

  useEffect(() => {
    const payment = searchParams.get('payment');
    if (payment === 'success') notify('Pago procesado. Tu suscripción se actualizará en breve.', 'success');
    if (payment === 'cancelled') notify('El cambio de suscripción fue cancelado.', 'warning');
  }, [notify, searchParams]);

  const planChanged = useMemo(() => selectedPlan !== currentPlan, [selectedPlan, currentPlan]);

  if (session.isLoading || detail.isLoading) return null;
  if (!session.data?.authenticated) return <Navigate to="/account/login" replace />;
  if (detail.isError || !detail.data) return <Navigate to="/account/dashboard" replace />;

  const { tenant, subscription } = detail.data;

  const changePlan = async () => {
    setBusy(true);
    try {
      const result = await accountPortalClient.changePlan(id, {
        plan: selectedPlan,
        gateway,
        billingCycle,
      });
      if (result.approvalUrl) {
        window.location.href = result.approvalUrl;
        return;
      }
      notify(result.message ?? 'Plan actualizado.', 'success');
      await queryClient.invalidateQueries({ queryKey: ['account-tenant', id] });
    } catch (error) {
      notify(error instanceof Error ? error.message : 'No se pudo cambiar el plan.', 'error');
    } finally {
      setBusy(false);
    }
  };

  const cancelSubscription = async () => {
    setBusy(true);
    try {
      const result = await accountPortalClient.cancelSubscription(id);
      notify(result.message, 'success');
      await queryClient.invalidateQueries({ queryKey: ['account-tenant', id] });
    } catch (error) {
      notify(error instanceof Error ? error.message : 'No se pudo cancelar.', 'error');
    } finally {
      setBusy(false);
    }
  };

  return (
    <AccountPortalLayout
      variant="app"
      wide
      showLogout
      backTo={{ label: 'Mis empresas', href: '/account/dashboard' }}
      title={tenant.name}
      subtitle={tenant.primary_domain ?? undefined}
      headerAction={
        tenant.primary_domain ? (
          <a
            href={`https://${tenant.primary_domain}`}
            target="_blank"
            rel="noopener noreferrer"
            className="ap-cta"
          >
            Abrir instancia
            <i className="ph ph-arrow-square-out" aria-hidden="true" />
          </a>
        ) : undefined
      }
    >
      <div className="ap-detail-grid">
        <div className="ap-panel">
          <h2>Suscripción actual</h2>
          {subscription ? (
            <>
              <div className="ap-info-row">
                <span>Plan</span>
                <StatusBadge value={subscription.plan} kind="plan" />
              </div>
              <div className="ap-info-row">
                <span>Ciclo</span>
                <span>{subscription.billing_cycle === 'annual' ? 'Anual' : 'Mensual'}</span>
              </div>
              <div className="ap-info-row">
                <span>Pasarela</span>
                <span>{subscription.gateway === 'paypal' ? 'PayPal' : 'MercadoPago'}</span>
              </div>
              <div className="ap-info-row">
                <span>Estado</span>
                <StatusBadge value={subscription.status} kind="status" />
              </div>
              {subscription.current_period_end ? (
                <div className="ap-info-row">
                  <span>Próximo cobro</span>
                  <span>{subscription.current_period_end}</span>
                </div>
              ) : null}
            </>
          ) : (
            <p className="ap-panel__desc">Sin suscripción activa — plan Free.</p>
          )}

          {subscription && ['active', 'past_due', 'pending'].includes(subscription.status) ? (
            <>
              <h2 style={{ marginTop: 24 }}>Cancelar suscripción</h2>
              <p className="ap-panel__desc">
                Al cancelar, tu empresa pasará al plan Free al final del periodo actual.
              </p>
              <Button type="button" variant="secondary" loading={busy} disabled={busy} onClick={cancelSubscription}>
                Cancelar suscripción
              </Button>
            </>
          ) : null}
        </div>

        <div className="ap-panel">
          <h2>Cambiar plan</h2>
          <p className="ap-panel__desc">Selecciona un nuevo plan y confirma el cambio.</p>
          <PlanSelector
            plan={selectedPlan}
            billingCycle={billingCycle}
            gateway={gateway}
            currentPlan={currentPlan}
            savingsLabel="-17%"
            onPlanChange={setSelectedPlan}
            onBillingCycleChange={setBillingCycle}
            onGatewayChange={setGateway}
          />
          <div style={{ marginTop: 16 }}>
            <Button
              type="button"
              variant="primary"
              loading={busy}
              disabled={busy || !planChanged}
              onClick={changePlan}
            >
              Cambiar plan
            </Button>
          </div>
        </div>
      </div>
    </AccountPortalLayout>
  );
}