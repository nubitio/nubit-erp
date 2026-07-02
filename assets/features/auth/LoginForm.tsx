import React, { useCallback, useEffect, useRef, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { Button, IconButton, TextField } from '@nubitio/ui';

import './LoginForm.scss';
import { authService } from '@features/auth';
import { branchService } from '@features/branch';
// eslint-disable-next-line no-restricted-imports -- LoginForm is the injection point for BranchPicker; this boundary is intentional
import { BranchPicker } from '@app/shell/components/BranchPicker';
import { resolveLoginRedirect, resolvePostLoginRedirect } from '@shared/lib/authRedirect';
import { requestAuthSessionSync } from '@shared/lib/authSessionSync';
import { StorageUtils } from '@shared/lib/StorageUtils';

interface BranchOption {
  id: string;
  name: string;
}

/** Extract a user-facing message from an API error response. */
function extractApiError(err: unknown, fallback: string): string {
  const anyErr = err as { data?: { detail?: string; message?: string } };
  return anyErr?.data?.detail ?? anyErr?.data?.message ?? fallback;
}

export const LoginForm = () => {
  const location = useLocation();
  const navigate = useNavigate();
  const usernameRef = useRef<HTMLInputElement>(null);
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [showLocalPicker, setShowLocalPicker] = useState(false);
  const [branchOptions, setBranchOptions] = useState<BranchOption[]>([]);
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [usernameError, setUsernameError] = useState<string | null>(null);
  const [passwordError, setPasswordError] = useState<string | null>(null);
  const [redirectPath] = useState(() =>
    resolveLoginRedirect(location.search, window.location.hash),
  );

  useEffect(() => {
    usernameRef.current?.focus();
  }, []);

  const clearFieldErrors = useCallback(() => {
    setUsernameError(null);
    setPasswordError(null);
    if (submitError) setSubmitError(null);
  }, [submitError]);

  const applyBranchAndRedirect = useCallback(
    async (branch: BranchOption) => {
      try {
        await authService.selectBranch(branch.id);
        branchService.setSelectedBranchId(branch.id);
        branchService.setSelectedBranchName(branch.name);
        const nextPath = resolvePostLoginRedirect(redirectPath);
        requestAuthSessionSync();
        navigate(nextPath, { replace: true });
      } catch (err) {
        setSubmitError(
          extractApiError(err, 'No se pudo seleccionar el local. Inténtalo nuevamente.'),
        );
        setLoading(false);
      }
    },
    [navigate, redirectPath],
  );

  const onSubmit = useCallback(
    async (e: React.FormEvent<HTMLFormElement>) => {
      e.preventDefault();
      if (loading) return;

      // client-side validation
      let valid = true;
      if (!username.trim()) {
        setUsernameError('Usuario es requerido');
        valid = false;
      }
      if (!password) {
        setPasswordError('Contraseña es requerida');
        valid = false;
      }
      if (!valid) {
        setSubmitError('Completa los campos requeridos para continuar.');
        return;
      }

      setSubmitError(null);
      setUsernameError(null);
      setPasswordError(null);
      setBranchOptions([]);
      setLoading(true);

      try {
        const response = await authService.login(username.trim(), password);

        if (response.data.user) {
          StorageUtils.setItem('user', response.data.user);
        }

        const selectedBranch = response.data.branch;
        if (selectedBranch?.id) {
          await applyBranchAndRedirect({ id: selectedBranch.id, name: selectedBranch.name ?? 'Local' });
          return;
        }

        try {
          const branchesResponse = await branchService.getAll();
          const rawBranches = branchesResponse?.data;
          const branches: BranchOption[] = Array.isArray(rawBranches) ? (rawBranches as BranchOption[]) : [];

          if (branches.length === 0) {
            setSubmitError('No hay locales disponibles para este usuario.');
            setLoading(false);
            return;
          }

          if (branches.length === 1) {
            await applyBranchAndRedirect(branches[0]);
            return;
          }

          setBranchOptions(branches);
          setShowLocalPicker(true);
          setLoading(false);
        } catch (err) {
          setSubmitError(extractApiError(err, 'No se pudo cargar la lista de locales.'));
          setLoading(false);
        }
      } catch (err) {
        setSubmitError(
          extractApiError(err, 'No se pudo iniciar sesión. Verifica tus credenciales e inténtalo de nuevo.'),
        );
        setLoading(false);
      }
    },
    [loading, username, password, applyBranchAndRedirect],
  );

  return (
    <form className="login-form" onSubmit={onSubmit} noValidate>
      {/* Username */}
      <div className={`login-field${usernameError ? ' login-field--error' : ''}`}>
        <TextField
          ref={usernameRef}
          id="login-username"
          className="login-input"
          type="text"
          placeholder="Usuario"
          autoComplete="username"
          aria-label="Usuario"
          invalid={!!usernameError}
          aria-invalid={!!usernameError}
          aria-describedby={usernameError ? 'login-username-error' : undefined}
          disabled={loading}
          value={username}
          onChange={(e) => {
            setUsername(e.target.value);
            clearFieldErrors();
          }}
        />
        {usernameError && (
          <span id="login-username-error" className="login-field__error" role="alert">
            {usernameError}
          </span>
        )}
      </div>

      {/* Password */}
      <div className={`login-field${passwordError ? ' login-field--error' : ''}`}>
        <div className="login-input-wrapper">
          <TextField
            id="login-password"
            className="login-input"
            type={showPassword ? 'text' : 'password'}
            placeholder="Contraseña"
            autoComplete="current-password"
            aria-label="Contraseña"
            invalid={!!passwordError}
            aria-invalid={!!passwordError}
            aria-describedby={passwordError ? 'login-password-error' : undefined}
            disabled={loading}
            value={password}
            onChange={(e) => {
              setPassword(e.target.value);
              clearFieldErrors();
            }}
          />
          <IconButton
            className="login-password-toggle"
            label={showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'}
            tabIndex={0}
            icon={showPassword ? 'ph ph-eye' : 'ph ph-eye-slash'}
            onClick={() => setShowPassword((v) => !v)}
          />
        </div>
        {passwordError && (
          <span id="login-password-error" className="login-field__error" role="alert">
            {passwordError}
          </span>
        )}
      </div>

      {/* Submit */}
      <Button type="submit" className="login-submit" variant="primary" fullWidth loading={loading}>
        Ingresar
      </Button>

      {/* General error */}
      {submitError && (
        <div className="login-error" role="alert" aria-live="assertive">
          {submitError}
        </div>
      )}

      <BranchPicker
        visible={showLocalPicker}
        setVisible={setShowLocalPicker}
        initialBranches={branchOptions}
        redirectPath={redirectPath}
      />
    </form>
  );
};
