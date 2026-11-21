import { useState } from 'react';
import { Button } from '@nubitio/ui';
import { ChangePasswordModal } from '@app/shell/components/ChangePasswordModal';
import { TwoFactorModal } from '@app/shell/components/TwoFactorModal';
import { ActiveSessions } from '../ActiveSessions';
import { SettingsGroup, SettingsRow } from '../settings/SettingsRow';

/** Password, second factor and open sessions: what protects the account. */
export const SecuritySection = () => {
  const [passwordOpen, setPasswordOpen] = useState(false);
  const [twoFactorOpen, setTwoFactorOpen] = useState(false);
  // Set by the page template; the second factor and the session list only exist with the identity module.
  const identity = Boolean(window.app_identity);

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
            description="Un código de tu teléfono además de la contraseña."
          >
            <Button
              type="button"
              variant="secondary"
              size="sm"
              onClick={() => setTwoFactorOpen(true)}
            >
              Configurar
            </Button>
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
        <TwoFactorModal visible={twoFactorOpen} onClose={() => setTwoFactorOpen(false)} />
      )}
    </>
  );
};
