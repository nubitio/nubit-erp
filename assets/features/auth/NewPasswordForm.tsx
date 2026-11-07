import React, { useState } from 'react';
import { Button, TextField } from '@nubitio/ui';

import './LoginForm.scss';
import { validateNewPassword } from './identityErrors';

interface NewPasswordFormProps {
  submitLabel: string;
  /** Resolves on success; throws to show an error message from the caller. */
  onSubmit: (password: string) => Promise<void>;
  errorMessage?: string | null;
}

/** New password + confirmation, shared by invitation acceptance and password reset. */
export const NewPasswordForm = ({ submitLabel, onSubmit, errorMessage }: NewPasswordFormProps) => {
  const [password, setPassword] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const [loading, setLoading] = useState(false);
  const [localError, setLocalError] = useState<string | null>(null);

  const handle = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (loading) return;
    const problem = validateNewPassword(password, confirmation);
    if (problem) {
      setLocalError(problem);
      return;
    }
    setLocalError(null);
    setLoading(true);
    try {
      await onSubmit(password);
    } finally {
      setLoading(false);
    }
  };

  const error = localError ?? errorMessage ?? null;

  return (
    <form className="login-form" onSubmit={handle} noValidate>
      <div className="login-field">
        <label className="login-label" htmlFor="new-password">
          Nueva contraseña
        </label>
        <TextField
          id="new-password"
          className="login-input"
          type="password"
          autoComplete="new-password"
          aria-label="Nueva contraseña"
          disabled={loading}
          value={password}
          onChange={(e) => setPassword(e.target.value)}
        />
        <span className="login-field__hint">Mínimo 8 caracteres.</span>
      </div>
      <div className="login-field">
        <label className="login-label" htmlFor="new-password-confirm">
          Repite la contraseña
        </label>
        <TextField
          id="new-password-confirm"
          className="login-input"
          type="password"
          autoComplete="new-password"
          aria-label="Repite la contraseña"
          disabled={loading}
          value={confirmation}
          onChange={(e) => setConfirmation(e.target.value)}
        />
      </div>
      <Button type="submit" className="login-submit" variant="primary" fullWidth loading={loading}>
        {submitLabel}
      </Button>
      {error && (
        <div className="login-error" role="alert" aria-live="assertive">
          {error}
        </div>
      )}
    </form>
  );
};
