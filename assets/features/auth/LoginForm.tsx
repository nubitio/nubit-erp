import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { Button, IconButton, TextField } from '@nubitio/ui';

import './LoginForm.scss';
import { authService } from '@features/auth';
import { branchService } from '@features/branch';
// eslint-disable-next-line no-restricted-imports -- LoginForm is the injection point for BranchPicker; this boundary is intentional
import { BranchPicker } from '@app/shell/components/BranchPicker';
import { resolveLoginRedirect, resolvePostLoginRedirect } from '@shared/lib/authRedirect';
import { requestAuthSessionSync } from '@shared/lib/authSessionSync';
import { StorageUtils } from '@shared/lib/StorageUtils';
import { classifyLoginError } from './loginErrors';

interface BranchOption {
  id: string;
  name: string;
  isActive?: boolean;
}

/** Extract a user-facing message from an API error response. */
function extractApiError(err: unknown, fallback: string): string {
  const anyErr = err as { data?: { detail?: string; message?: string } };
  return anyErr?.data?.detail ?? anyErr?.data?.message ?? fallback;
}

/** The raw server message key, which is what the second-factor step branches on. */
function rawApiMessage(err: unknown): string | undefined {
  return (err as { data?: { message?: string } })?.data?.message;
}

export const LoginForm = () => {
  const location = useLocation();
  const navigate = useNavigate();
  const usernameRef = useRef<HTMLInputElement>(null);
  const totpRef = useRef<HTMLInputElement>(null);
  const [needsCode, setNeedsCode] = useState(false);
  const [totpCode, setTotpCode] = useState('');
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [showLocalPicker, setShowLocalPicker] = useState(false);
  const [branchOptions, setBranchOptions] = useState<BranchOption[]>([]);
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [usernameError, setUsernameError] = useState<string | null>(null);
  const [passwordError, setPasswordError] = useState<string | null>(null);
  const [redirectPath] = useState(() => resolveLoginRedirect(location.search));

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
      if (needsCode && !totpCode.trim()) {
        setSubmitError('Ingresa el código de verificación para continuar.');
        return;
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
        const response = await authService.login(
          username.trim(),
          password,
          needsCode ? totpCode.trim() : undefined,
        );

        if (response.data.user) {
          StorageUtils.setItem('user', response.data.user);
        }

        const selectedBranch = response.data.branch;
        if (selectedBranch?.id) {
          await applyBranchAndRedirect({
            id: selectedBranch.id,
            name: selectedBranch.name ?? 'Local',
          });
          return;
        }

        try {
          const branchesResponse = await branchService.getAll();
          const rawBranches = branchesResponse?.data;
          const allBranches: BranchOption[] = Array.isArray(rawBranches)
            ? (rawBranches as BranchOption[])
            : [];
          // Disabled branches stay available for historical reports but cannot start a session.
          const branches = allBranches.filter((branch) => branch.isActive !== false);

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
        const classified = classifyLoginError(rawApiMessage(err));
        if (classified.kind === 'needs-code') {
          // Password was right; ask for the second factor instead of reporting a failure.
          setNeedsCode(true);
          setSubmitError(null);
          setTimeout(() => totpRef.current?.focus(), 0);
        } else if (classified.text) {
          if (classified.kind === 'bad-code') setTotpCode('');
          setSubmitError(classified.text);
        } else {
          setSubmitError(
            extractApiError(
              err,
              'No se pudo iniciar sesión. Verifica tus credenciales e inténtalo de nuevo.',
            ),
          );
        }
        setLoading(false);
      }
    },
    [loading, username, password, needsCode, totpCode, applyBranchAndRedirect],
  );

  return (
    <form className="login-form" onSubmit={onSubmit} noValidate>
      {/* Username */}
      <div className={`login-field${usernameError ? ' login-field--error' : ''}`}>
        <label className="login-label" htmlFor="login-username">
          Usuario
        </label>
        <TextField
          ref={usernameRef}
          id="login-username"
          className="login-input"
          type="text"
          autoComplete="username"
          aria-label="Usuario"
          invalid={!!usernameError}
          aria-invalid={!!usernameError}
          aria-describedby={usernameError ? 'login-username-error' : undefined}
          disabled={loading || needsCode}
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
        <label className="login-label" htmlFor="login-password">
          Contraseña
        </label>
        <div className="login-input-wrapper">
          <TextField
            id="login-password"
            className="login-input"
            type={showPassword ? 'text' : 'password'}
            autoComplete="current-password"
            aria-label="Contraseña"
            invalid={!!passwordError}
            aria-invalid={!!passwordError}
            aria-describedby={passwordError ? 'login-password-error' : undefined}
            disabled={loading || needsCode}
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

      {/* Second factor (only after the server asked for it) */}
      {needsCode && (
        <div className="login-field">
          <label className="login-label" htmlFor="login-totp">
            Código de verificación
          </label>
          <TextField
            ref={totpRef}
            id="login-totp"
            className="login-input"
            type="text"
            inputMode="text"
            autoComplete="one-time-code"
            aria-label="Código de verificación"
            disabled={loading}
            value={totpCode}
            onChange={(e) => {
              setTotpCode(e.target.value);
              if (submitError) setSubmitError(null);
            }}
          />
          <span className="login-field__hint">
            Ingresa el código de 6 dígitos de tu app de autenticación, o un código de recuperación.
          </span>
          <button
            type="button"
            className="login-link"
            onClick={() => {
              setNeedsCode(false);
              setTotpCode('');
              setSubmitError(null);
            }}
          >
            Usar otra cuenta
          </button>
        </div>
      )}

      {/* Submit */}
      <Button type="submit" className="login-submit" variant="primary" fullWidth loading={loading}>
        {needsCode ? 'Verificar' : 'Ingresar'}
      </Button>

      {/* General error */}
      {submitError && (
        <div className="login-error" role="alert" aria-live="assertive">
          {submitError}
        </div>
      )}

      {window.app_identity && !needsCode && (
        <Link className="login-link" to="/forgot-password">
          ¿Olvidaste tu contraseña?
        </Link>
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
