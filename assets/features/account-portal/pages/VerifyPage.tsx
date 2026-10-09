import { useMemo, useState } from 'react';
import { Link, Navigate, useNavigate, useSearchParams } from 'react-router-dom';
import { Button, FormField, TextField } from '@nubitio/ui';
import { AccountPortalLayout } from '../components/AccountPortalLayout';
import { accountPortalClient } from '../api/accountPortalClient';
import { useAccountSession, useInvalidateAccountSession } from '../hooks/useAccountSession';
import { useApToast } from '../providers/ApToastProvider';

export function VerifyPage() {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const session = useAccountSession();
  const invalidateSession = useInvalidateAccountSession();
  const { notify } = useApToast();
  const email = useMemo(
    () => searchParams.get('email') ?? session.data?.pendingEmail ?? '',
    [searchParams, session.data?.pendingEmail],
  );
  const [code, setCode] = useState('');
  const [busy, setBusy] = useState(false);

  if (session.data?.authenticated) {
    return <Navigate to="/register/new-tenant" replace />;
  }

  const submit = async (event: React.FormEvent) => {
    event.preventDefault();
    setBusy(true);
    try {
      await accountPortalClient.verify(email, code);
      await invalidateSession();
      notify('Email verificado. Ya puedes crear tu empresa.', 'success');
      navigate('/register/new-tenant', { replace: true });
    } catch (error) {
      notify(error instanceof Error ? error.message : 'Código inválido.', 'error');
    } finally {
      setBusy(false);
    }
  };

  return (
    <AccountPortalLayout
      variant="auth"
      funnelStep={2}
      title="Verifica tu email"
      subtitle={
        email ? `Ingresa el código enviado a ${email}` : 'Ingresa el código de verificación.'
      }
    >
      <form className="ap-form" onSubmit={submit}>
        <input type="hidden" name="email" value={email} />
        <FormField label="Código de 6 dígitos">
          <TextField
            value={code}
            onChange={(e) => setCode(e.target.value.replace(/\D/g, '').slice(0, 6))}
            inputMode="numeric"
            pattern="\d{6}"
            placeholder="000000"
            required
          />
        </FormField>
        <Button type="submit" variant="primary" loading={busy} disabled={busy || email === ''}>
          Verificar
        </Button>
      </form>
      <p className="ap-footer-link">
        <Link to="/register">Volver al registro</Link>
      </p>
    </AccountPortalLayout>
  );
}
