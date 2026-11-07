import React, { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { Button, TextField } from '@nubitio/ui';

import './LoginForm.scss';
import { AuthShell } from './AuthShell';
import { NewPasswordForm } from './NewPasswordForm';
import { describeIdentityError } from './identityErrors';
import { authService } from './services/authService';

const BackToLogin = () => (
  <p className="login-field__hint">
    <Link className="login-link" to="/login">
      Volver a ingresar
    </Link>
  </p>
);

/** `#/forgot-password`: asks for the user name or e-mail. The answer never says if it exists. */
export const ForgotPasswordPage = () => {
  const [value, setValue] = useState('');
  const [loading, setLoading] = useState(false);
  const [sent, setSent] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const submit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (loading || !value.trim()) return;
    setLoading(true);
    setError(null);
    try {
      await authService.forgotPassword(value.trim());
      setSent(true);
    } catch (err) {
      setError(describeIdentityError(err, 'No se pudo enviar la solicitud. Inténtalo de nuevo.'));
    } finally {
      setLoading(false);
    }
  };

  return (
    <AuthShell title="Restablecer contraseña" subtitle="Te enviaremos un enlace a tu correo.">
      {sent ? (
        <div className="login-form" role="status">
          <p>
            Si la cuenta existe y tiene un correo registrado, recibirás un enlace para elegir una
            nueva contraseña. Revisa también tu carpeta de spam.
          </p>
          <BackToLogin />
        </div>
      ) : (
        <form className="login-form" onSubmit={submit} noValidate>
          <div className="login-field">
            <label className="login-label" htmlFor="forgot-identifier">
              Usuario o correo
            </label>
            <TextField
              id="forgot-identifier"
              className="login-input"
              type="text"
              autoComplete="username"
              aria-label="Usuario o correo"
              disabled={loading}
              value={value}
              onChange={(e) => setValue(e.target.value)}
            />
          </div>
          <Button
            type="submit"
            className="login-submit"
            variant="primary"
            fullWidth
            loading={loading}
          >
            Enviar enlace
          </Button>
          {error && (
            <div className="login-error" role="alert" aria-live="assertive">
              {error}
            </div>
          )}
          <BackToLogin />
        </form>
      )}
    </AuthShell>
  );
};

/** `#/reset-password?token=…`: the link from the recovery e-mail. */
export const ResetPasswordPage = () => {
  const token = useSearchParams()[0].get('token') ?? '';
  const [done, setDone] = useState(false);
  const [error, setError] = useState<string | null>(null);

  if (!token) {
    return (
      <AuthShell title="Enlace no válido">
        <div className="login-form" role="alert">
          <p>El enlace está incompleto. Solicita uno nuevo desde la pantalla de ingreso.</p>
          <BackToLogin />
        </div>
      </AuthShell>
    );
  }

  return (
    <AuthShell title="Elige una nueva contraseña">
      {done ? (
        <div className="login-form" role="status">
          <p>Tu contraseña se actualizó. Ya puedes ingresar con la nueva.</p>
          <BackToLogin />
        </div>
      ) : (
        <NewPasswordForm
          submitLabel="Guardar contraseña"
          errorMessage={error}
          onSubmit={async (password) => {
            setError(null);
            try {
              await authService.resetPassword(token, password);
              setDone(true);
            } catch (err) {
              setError(describeIdentityError(err, 'No se pudo cambiar la contraseña.'));
            }
          }}
        />
      )}
    </AuthShell>
  );
};

/** `#/accept-invitation?token=…`: the invitee chooses their own password. */
export const AcceptInvitationPage = () => {
  const token = useSearchParams()[0].get('token') ?? '';
  const [email, setEmail] = useState<string | null>(null);
  const [invalid, setInvalid] = useState<string | null>(
    token ? null : 'El enlace está incompleto.',
  );
  const [done, setDone] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!token) return;
    authService
      .previewInvitation(token)
      .then((response) => setEmail(response.data.email))
      .catch((err) =>
        setInvalid(describeIdentityError(err, 'Esta invitación no es válida o venció.')),
      );
  }, [token]);

  if (invalid) {
    return (
      <AuthShell title="Invitación no válida">
        <div className="login-form" role="alert">
          <p>{invalid} Pide una nueva a tu administrador.</p>
          <BackToLogin />
        </div>
      </AuthShell>
    );
  }

  return (
    <AuthShell
      title="Te invitaron a Nubit ERP"
      subtitle={
        done
          ? undefined
          : email
            ? `Crea tu contraseña para ${email}.`
            : 'Verificando tu invitación…'
      }
    >
      {done ? (
        <div className="login-form" role="status">
          <p>
            Tu cuenta está lista. Tu usuario es <strong>{done}</strong>.
          </p>
          <BackToLogin />
        </div>
      ) : email ? (
        <NewPasswordForm
          submitLabel="Crear cuenta"
          errorMessage={error}
          onSubmit={async (password) => {
            setError(null);
            try {
              const response = await authService.acceptInvitation(token, password);
              setDone(response.data.username);
            } catch (err) {
              setError(describeIdentityError(err, 'No se pudo crear la cuenta.'));
            }
          }}
        />
      ) : null}
    </AuthShell>
  );
};
