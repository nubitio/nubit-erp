import { useState } from 'react';
import { Link, Navigate, useNavigate } from 'react-router-dom';
import { Button, FormField, TextField } from '@nubitio/ui';
import { AccountPortalLayout } from '../components/AccountPortalLayout';
import { accountPortalClient } from '../api/accountPortalClient';
import { useAccountSession } from '../hooks/useAccountSession';
import { useApToast } from '../providers/ApToastProvider';

export function RegisterPage() {
  const navigate = useNavigate();
  const session = useAccountSession();
  const { notify } = useApToast();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [confirm, setConfirm] = useState('');
  const [busy, setBusy] = useState(false);

  if (session.data?.authenticated) {
    return <Navigate to="/register/new-tenant" replace />;
  }

  const submit = async (event: React.FormEvent) => {
    event.preventDefault();
    if (password !== confirm) {
      notify('Las contraseñas no coinciden.', 'error');
      return;
    }
    setBusy(true);
    try {
      const result = await accountPortalClient.register(email, password);
      notify('Te enviamos un código de 6 dígitos por email.', 'success');
      navigate(`/register/verify?email=${encodeURIComponent(result.email)}`);
    } catch (error) {
      notify(error instanceof Error ? error.message : 'No se pudo registrar.', 'error');
    } finally {
      setBusy(false);
    }
  };

  return (
    <AccountPortalLayout
      variant="auth"
      funnelStep={1}
      title="Crear cuenta"
      subtitle="Empieza gratis con eFact en minutos."
    >
      <form className="ap-form" onSubmit={submit}>
        <FormField label="Email">
          <TextField type="email" value={email} onChange={(e) => setEmail(e.target.value)} autoComplete="email" required />
        </FormField>
        <FormField label="Contraseña">
          <TextField type="password" value={password} onChange={(e) => setPassword(e.target.value)} autoComplete="new-password" minLength={8} required />
        </FormField>
        <FormField label="Confirmar contraseña">
          <TextField type="password" value={confirm} onChange={(e) => setConfirm(e.target.value)} autoComplete="new-password" minLength={8} required />
        </FormField>
        <Button type="submit" variant="primary" loading={busy} disabled={busy}>
          Continuar
        </Button>
      </form>
      <p className="ap-footer-link">
        ¿Ya tienes cuenta? <Link to="/account/login">Inicia sesión</Link>
      </p>
    </AccountPortalLayout>
  );
}