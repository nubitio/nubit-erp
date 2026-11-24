import { useEffect, useState } from 'react';
import { useLocation, useNavigate, useParams } from 'react-router-dom';
import toast from '@shared/lib/SharedUtils';
import { cashboxService } from './services/cashboxService';
import { CashCloseFlow } from './components/CashCloseFlow';

type CashClosureParams = {
  id: string;
};

/**
 * `/cash-session/:id/close` closes a session. The older `/cash-register/:id/close` carries a register
 * id, so the open session is looked up first.
 */
export const CashRegisterClose = () => {
  const { id } = useParams<CashClosureParams>();
  const location = useLocation();
  const navigate = useNavigate();
  const isLegacyCashRegisterRoute = location.pathname.startsWith('/cash-register/');
  const [legacySession, setLegacySession] = useState<{ registerId: string; sessionId: string }>();

  useEffect(() => {
    if (id === undefined || !isLegacyCashRegisterRoute) return;
    let cancelled = false;

    cashboxService
      .getDashboard()
      .then((response) => {
        if (cancelled) return;
        const cashRegister = response.data.find((item) => item.id === Number(id));
        const sessionId = cashRegister?.cashSessionId ?? cashRegister?.cashShiftId;
        if (sessionId === null || sessionId === undefined) {
          toast('No hay una sesión abierta para cerrar esta caja', 'error');
          navigate('/cashbox');
          return;
        }
        setLegacySession({ registerId: id, sessionId: String(sessionId) });
      })
      .catch(() => toast('No se pudo cargar el resumen de la caja', 'error'));

    return () => {
      cancelled = true;
    };
  }, [id, isLegacyCashRegisterRoute, navigate]);

  const legacySessionId = legacySession?.registerId === id ? legacySession?.sessionId : undefined;
  const sessionId = isLegacyCashRegisterRoute ? legacySessionId : id;

  if (sessionId === undefined) {
    return (
      <div className="cash-close-page">
        <p role="status">Cargando datos...</p>
      </div>
    );
  }

  return (
    <CashCloseFlow
      key={sessionId}
      sessionId={sessionId}
      onClosed={() => navigate('/cashbox/dashboard')}
      onContinue={() => navigate('/pos')}
      onCancel={() => navigate(`/cash-session/${sessionId}/movements`)}
    />
  );
};
