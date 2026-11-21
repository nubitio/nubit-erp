import { useCallback, useEffect, useState } from 'react';
import { Badge, Button, Skeleton } from '@nubitio/ui';
import { authService } from '@features/auth';
import { ChangePasswordModal } from '@app/shell/components/ChangePasswordModal';
import { TwoFactorModal } from '@app/shell/components/TwoFactorModal';
import { ActiveSessions } from '../ActiveSessions';
import { SettingsGroup, SettingsRow } from '../settings/SettingsRow';
import { describeTotp, type TotpStatus } from '../totpStatus';

/** Password, second factor and open sessions: what protects the account. */
export const SecuritySection = () => {
  const [passwordOpen, setPasswordOpen] = useState(false);
  const [twoFactorOpen, setTwoFactorOpen] = useState(false);
  // Set by the page template; the second factor and the session list only exist with the identity module.
  const identity = Boolean(window.app_identity);

  // undefined = still loading, null = could not be read (the row then falls back to a plain button).
  const [totp, setTotp] = useState<TotpStatus | null | undefined>(undefined);
  const loadTotp = useCallback(async () => {
    try {
      setTotp((await authService.totpStatus()).data);
    } catch {
      setTotp(null);
    }
  }, []);

  useEffect(() => {
    if (identity) queueMicrotask(() => void loadTotp());
  }, [identity, loadTotp]);

  const row = totp ? describeTotp(totp) : null;

  return (
    <>
      <SettingsGroup>
        <SettingsRow label="Contraseña" description="Cámbiala si crees que otra persona la conoce.">
          <Button type="button" variant="secondary" size="sm" onClick={() => setPasswordOpen(true)}>
            Cambiar contraseña
          </Button>
        </SettingsRow>
        {identity && (
          <SettingsRow
            label="Verificación en dos pasos"
            description={row?.description ?? 'Un código de tu teléfono además de la contraseña.'}
          >
            {totp === undefined ? (
              <Skeleton variant="rect" width={96} height={28} />
            ) : (
              <>
                {row && (
                  <span data-testid="two-factor-status">
                    <Badge variant={row.badge.tone} pill>
                      {row.badge.label}
                    </Badge>
                  </span>
                )}
                <Button
                  type="button"
                  variant="secondary"
                  size="sm"
                  onClick={() => setTwoFactorOpen(true)}
                >
                  {row?.action ?? 'Configurar'}
                </Button>
              </>
            )}
          </SettingsRow>
        )}
      </SettingsGroup>

      {identity && (
        <SettingsGroup title="Dónde has iniciado sesión">
          <ActiveSessions />
        </SettingsGroup>
      )}

      <ChangePasswordModal visible={passwordOpen} onClose={() => setPasswordOpen(false)} />
      {identity && (
        <TwoFactorModal
          visible={twoFactorOpen}
          onClose={() => {
            setTwoFactorOpen(false);
            // Turning it on or off happens inside the dialog: re-read the state when it closes.
            void loadTotp();
          }}
        />
      )}
    </>
  );
};
