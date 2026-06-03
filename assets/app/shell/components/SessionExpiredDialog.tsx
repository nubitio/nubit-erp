import { useCallback, useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useScreenSize } from '@nubit/admin';
import { Button } from '@nubit/ui';

import { useAuth } from '@app/contexts/auth';
import { SESSION_EXPIRED_EVENT } from '@shared/lib/sessionExpiredEvent';
import { AppDialog } from './AppDialog';

import './SessionExpiredDialog.scss';

export const SessionExpiredDialog = () => {
  const navigate = useNavigate();
  const { signOut } = useAuth();
  const { isXSmall } = useScreenSize();
  const [visible, setVisible] = useState(false);

  useEffect(() => {
    const handleSessionExpired = () => setVisible(true);

    window.addEventListener(SESSION_EXPIRED_EVENT, handleSessionExpired);
    return () => window.removeEventListener(SESSION_EXPIRED_EVENT, handleSessionExpired);
  }, []);

  const handleConfirm = useCallback(() => {
    setVisible(false);
    signOut();
    navigate('/login', { replace: true });
  }, [navigate, signOut]);

  return (
    <AppDialog
      title="Sesión expirada"
      visible={visible}
      width={420}
      height="auto"
      fullScreen={isXSmall}
      showCloseButton={false}
      closeOnEscape={false}
      closeOnOutsideClick={false}
      onClose={() => undefined}
      className="session-expired-dialog"
      footer={
        <Button variant="primary" onClick={handleConfirm}>
          Ir al login
        </Button>
      }
    >
      <div className="session-expired-dialog__content">
        <div className="session-expired-dialog__icon" aria-hidden="true">
          <i className="ph ph-lock-key" />
        </div>
        <div className="session-expired-dialog__body">
          <p className="session-expired-dialog__title">Tu sesión terminó.</p>
          <p className="session-expired-dialog__message">
            Vuelve a iniciar sesión para seleccionar tu local y continuar trabajando.
          </p>
        </div>
      </div>
    </AppDialog>
  );
};
