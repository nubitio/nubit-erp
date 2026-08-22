import { useState } from 'react';
import { Link, Navigate, useNavigate } from 'react-router-dom';
import { Button, FormField, TextField } from '@nubitio/ui';
import { AccountPortalLayout } from '../components/AccountPortalLayout';
import { accountPortalClient } from '../api/accountPortalClient';
import { useAccountSession, useInvalidateAccountSession } from '../hooks/useAccountSession';
import { useApToast } from '../providers/ApToastProvider';

export function LoginPage() {
  const navigate = useNavigate();
  const session = useAccountSession();
  const invalidateSession = useInvalidateAccountSession();
  const { notify } = useApToast();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [busy, setBusy] = useState(false);

  if (session.data?.authenticated) {
    return <Navigate to="/account/dashboard" replace />;
  }

  const submit = async (event: React.FormEvent) => {
    event.preventDefault();
    setBusy(true);
    try {
      await accountPortalClient.login(email, password);
      await invalidateSession();
      navigate('/account/dashboard', { replace: true });
    } catch (error) {
      const message = error instanceof Error ? error.message : 'No se pudo iniciar sesión.';
      notify(message, 'error');
      if (message.includes('verificado')) {
        navigate(`/register/verify?email=${encodeURIComponent(email)}`);
      }
    } finally {
      setBusy(false);
    }
  };

  return (
    <AccountPortalLayout
      variant="auth"
      title="Bienvenido de nuevo"
      subtitle="Accede a tus empresas en Nubit ERP."
      brandHeadline={'Gestiona todas tus\nempresas en un solo lugar.'}
      brandDescription="Inicia sesión para administrar suscripciones, planes y accesos a tus instancias."
    >
      <form className="ap-form" onSubmit={submit}>
        <FormField label="Email">
          <TextField type="email" value={email} onChange={(e) => setEmail(e.target.value)} autoComplete="email" required />
        </FormField>
        <FormField label="Contraseña">
          <TextField type="password" value={password} onChange={(e) => setPassword(e.target.value)} autoComplete="current-password" required />
        </FormField>
        <Button type="submit" variant="primary" loading={busy} disabled={busy}>
          Entrar
        </Button>
      </form>
      <p className="ap-footer-link">
        ¿No tienes cuenta? <Link to="/register">Regístrate</Link>
      </p>
    </AccountPortalLayout>
  );
}
