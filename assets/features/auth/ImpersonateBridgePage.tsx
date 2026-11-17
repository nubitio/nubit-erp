import { useCallback, useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';

import { authService } from '@features/auth';
import { branchService } from '@features/branch';
import { requestAuthSessionSync } from '@shared/lib/authSessionSync';
import { useFragmentParams } from '@shared/lib/useFragmentParams';
import { StorageUtils } from '@shared/lib/StorageUtils';
// eslint-disable-next-line no-restricted-imports -- reuses the shared branch-selection modal, same boundary exception LoginForm takes for BranchPicker
import { BranchPicker } from '@app/shell/components/BranchPicker';

import './LoginPage.scss';

/** The support token travels in the fragment, never the path or query (ADR-020, D3). */
const TOKEN_PARAM = ['token'] as const;

interface BranchOption {
  id: string;
  name: string;
  isActive?: boolean;
}

function extractErrorMessage(err: unknown, fallback: string): string {
  const anyErr = err as { data?: { error?: string; detail?: string; message?: string } };
  return anyErr?.data?.error ?? anyErr?.data?.detail ?? anyErr?.data?.message ?? fallback;
}

export const ImpersonateBridgePage = () => {
  const navigate = useNavigate();
  // Captured once; the hook also removes it from the URL so it never lingers in
  // browser history or gets shared accidentally, even if consume fails.
  const { token } = useFragmentParams(TOKEN_PARAM);
  const [error, setError] = useState<string | null>(null);
  const [showPicker, setShowPicker] = useState(false);
  const [branchOptions, setBranchOptions] = useState<BranchOption[]>([]);

  const finishWithBranch = useCallback(
    async (branch: BranchOption) => {
      try {
        await authService.selectBranch(branch.id);
        branchService.setSelectedBranchId(branch.id);
        branchService.setSelectedBranchName(branch.name);
        requestAuthSessionSync();
        navigate('/', { replace: true });
      } catch (err) {
        setError(
          extractErrorMessage(err, 'No se pudo seleccionar el local. Inténtalo nuevamente.'),
        );
      }
    },
    [navigate],
  );

  useEffect(() => {
    if (!token) {
      // eslint-disable-next-line react-hooks/set-state-in-effect -- synchronous validation of a URL fragment read at mount, not a derived-state anti-pattern
      setError('Enlace de acceso inválido o incompleto.');
      return;
    }

    let cancelled = false;

    (async () => {
      try {
        const response = await authService.consumeImpersonationToken(token);
        if (cancelled) return;

        if (response.data.user) {
          StorageUtils.setItem('user', response.data.user);
        }

        const branchesResponse = await branchService.getAll();
        if (cancelled) return;

        const rawBranches = branchesResponse?.data;
        const allBranches: BranchOption[] = Array.isArray(rawBranches)
          ? (rawBranches as BranchOption[])
          : [];
        const branches = allBranches.filter((branch) => branch.isActive !== false);

        if (branches.length === 0) {
          setError('No hay locales disponibles para este usuario.');
          return;
        }

        if (branches.length === 1) {
          await finishWithBranch(branches[0]);
          return;
        }

        setBranchOptions(branches);
        setShowPicker(true);
      } catch (err) {
        if (cancelled) return;
        setError(extractErrorMessage(err, 'No se pudo iniciar la sesión de soporte.'));
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [finishWithBranch, token]);

  return (
    <div id="login-root" className="impersonate-bridge">
      <main className="login-form-panel">
        <div className="form-card">
          <header className="form-header">
            <h2>Sesión de soporte</h2>
            <p>{error ?? 'Iniciando sesión como el usuario indicado…'}</p>
          </header>
          {!error && (
            <div className="app-loading" role="status" aria-label="Cargando">
              <span className="app-loading__spinner" aria-hidden="true" />
            </div>
          )}
        </div>
      </main>
      <BranchPicker
        visible={showPicker}
        setVisible={setShowPicker}
        initialBranches={branchOptions}
        redirectPath="/"
      />
    </div>
  );
};
