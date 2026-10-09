/* eslint-disable react-hooks/set-state-in-effect -- wizard transitions intentionally synchronize local form state */
import { useEffect, useMemo, useState } from 'react';
import { Navigate, useNavigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { Button, FormField, TextField } from '@nubitio/ui';
import { AccountPortalLayout } from '../components/AccountPortalLayout';
import { PlanSelector } from '../components/PlanSelector';
import { accountPortalClient } from '../api/accountPortalClient';
import { useAccountSession } from '../hooks/useAccountSession';
import { useApToast } from '../providers/ApToastProvider';
import type { BillingCycle, PaymentGateway, PlanKey } from '../lib/plans';

function slugify(value: string) {
  return value
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 63);
}

export function WizardPage() {
  const navigate = useNavigate();
  const session = useAccountSession();
  const config = useQuery({ queryKey: ['account-config'], queryFn: accountPortalClient.getConfig });
  const { notify } = useApToast();

  const [tenantName, setTenantName] = useState('');
  const [subdomain, setSubdomain] = useState('');
  const [plan, setPlan] = useState<PlanKey>('free');
  const [gateway, setGateway] = useState<PaymentGateway>('mercadopago');
  const [billingCycle, setBillingCycle] = useState<BillingCycle>('monthly');
  const [adminPassword, setAdminPassword] = useState('');
  const [adminPasswordConfirm, setAdminPasswordConfirm] = useState('');
  const [subdomainHint, setSubdomainHint] = useState('');
  const [subdomainOk, setSubdomainOk] = useState(false);
  const [busy, setBusy] = useState(false);

  const passwordOk = useMemo(
    () => adminPassword.length >= 8 && adminPassword === adminPasswordConfirm,
    [adminPassword, adminPasswordConfirm],
  );

  const canSubmit = subdomainOk && passwordOk && tenantName.trim() !== '';

  useEffect(() => {
    const value = subdomain.trim().toLowerCase();
    if (!value) {
      setSubdomainOk(false);
      setSubdomainHint('');
      return;
    }

    const timer = window.setTimeout(async () => {
      try {
        const result = await accountPortalClient.checkSubdomain(value);
        if (result.available) {
          setSubdomainHint(`Disponible: ${result.domain}`);
          setSubdomainOk(true);
        } else {
          setSubdomainHint(result.reason || 'No disponible');
          setSubdomainOk(false);
        }
      } catch {
        setSubdomainHint('No se pudo verificar. Intenta de nuevo.');
        setSubdomainOk(false);
      }
    }, 400);

    return () => window.clearTimeout(timer);
  }, [subdomain]);

  if (session.isLoading) return null;
  if (!session.data?.authenticated) {
    return <Navigate to="/account/login" replace />;
  }

  const submit = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!canSubmit) return;
    setBusy(true);
    try {
      const result = await accountPortalClient.provision({
        tenantName: tenantName.trim(),
        subdomain: subdomain.trim(),
        plan,
        gateway,
        billingCycle,
        adminPassword,
      });
      if (result.requiresPayment && result.approvalUrl) {
        window.location.href = result.approvalUrl;
        return;
      }
      navigate(`/register/status/${result.token}`);
    } catch (error) {
      notify(error instanceof Error ? error.message : 'No se pudo crear la empresa.', 'error');
    } finally {
      setBusy(false);
    }
  };

  const domainSuffix = config.data?.domainSuffix ?? '.nubit.pe';
  const isPaid = plan !== 'free';

  return (
    <AccountPortalLayout
      variant="app"
      wide
      showLogout
      backTo={{ label: 'Mis empresas', href: '/account/dashboard' }}
      title="Configura tu empresa"
      subtitle={`Conectado como ${session.data.account?.email ?? ''}`}
    >
      <form className="ap-form" onSubmit={submit}>
        <section className="ap-form__section">
          <h3 className="ap-form__section-title">Datos de la empresa</h3>
          <p className="ap-form__section-desc">Elige un nombre y subdominio para tu instancia.</p>

          <FormField label="Nombre de la empresa">
            <TextField
              value={tenantName}
              onChange={(e) => setTenantName(e.target.value)}
              onBlur={() => {
                if (!subdomain.trim() && tenantName.trim()) {
                  setSubdomain(slugify(tenantName.trim()));
                }
              }}
              required
            />
          </FormField>

          <FormField label="Subdominio">
            <div className="ap-subdomain">
              <TextField
                value={subdomain}
                onChange={(e) =>
                  setSubdomain(e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, ''))
                }
                required
              />
              <span className="ap-subdomain__suffix">{domainSuffix}</span>
            </div>
            {subdomainHint ? (
              <p className={`ap-hint ${subdomainOk ? 'ap-hint--success' : 'ap-hint--error'}`}>
                {subdomainHint}
              </p>
            ) : null}
          </FormField>
        </section>

        <section className="ap-form__section">
          <h3 className="ap-form__section-title">Plan y facturación</h3>
          <p className="ap-form__section-desc">Puedes cambiar de plan en cualquier momento.</p>
          <PlanSelector
            plan={plan}
            billingCycle={billingCycle}
            gateway={gateway}
            onPlanChange={setPlan}
            onBillingCycleChange={setBillingCycle}
            onGatewayChange={setGateway}
          />
        </section>

        <section className="ap-form__section">
          <h3 className="ap-form__section-title">Acceso administrador</h3>
          <p className="ap-form__section-desc">Será el usuario admin dentro de tu instancia.</p>

          <FormField label="Contraseña del administrador">
            <TextField
              type="password"
              value={adminPassword}
              onChange={(e) => setAdminPassword(e.target.value)}
              minLength={8}
              autoComplete="new-password"
              required
            />
          </FormField>

          <FormField label="Confirmar contraseña">
            <TextField
              type="password"
              value={adminPasswordConfirm}
              onChange={(e) => setAdminPasswordConfirm(e.target.value)}
              minLength={8}
              autoComplete="new-password"
              required
            />
            {adminPasswordConfirm && !passwordOk ? (
              <p className="ap-hint ap-hint--error">
                Las contraseñas deben coincidir (mín. 8 caracteres).
              </p>
            ) : null}
          </FormField>
        </section>

        <Button type="submit" variant="primary" loading={busy} disabled={!canSubmit || busy}>
          {isPaid ? 'Continuar al pago' : 'Crear mi empresa'}
        </Button>
      </form>
    </AccountPortalLayout>
  );
}
