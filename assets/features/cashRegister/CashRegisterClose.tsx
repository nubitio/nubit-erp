import { useEffect, useRef, useState } from 'react';
import { httpClient } from '@shared/api/HttpClient';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { useLocation, useNavigate, useParams } from 'react-router-dom';
import toast from '@shared/lib/SharedUtils';
import cashRegisterService from './services/cashRegisterService';
import { cashboxService } from './services/cashboxService';
import { ConfirmDialog } from '@nubitio/ui';
import {
  CashClosureGrid,
  type CashClosureGridHandle,
  type CashClosureRow,
} from './components/CashClosureGrid';
import './CashRegisterClose.scss';

const API = getCoreApiBaseUrl();

type CashClosureParams = {
  id: string;
};

export const CashRegisterClose = () => {
  const { id } = useParams<CashClosureParams>();
  const location = useLocation();
  const gridRef = useRef<CashClosureGridHandle | null>(null);
  const [showCloseConfirm, setShowCloseConfirm] = useState(false);
  const [isClosing, setIsClosing] = useState(false);
  const [cashSessionId, setCashSessionId] = useState<string | null>(null);
  const navigate = useNavigate();
  const isLegacyCashRegisterRoute = location.pathname.startsWith('/cash-register/');

  const closeCashRegister = () => {
    const closureData = gridRef.current?.getRows();
    const differences = gridRef.current?.getDifferences() ?? [];
    if (cashSessionId === null || closureData === undefined) {
      toast('Error al cerrar la caja', 'error');
      return;
    }

    const data = closureData.map((item) => ({
      cashSession: API + 'cash-sessions/' + cashSessionId,
      paymentMethod: API + 'payment-methods/' + item.paymentMethodId,
      expectedAmount: Number(item.expectedAmount),
      countedAmount: item.countedAmount,
      difference: differences.find((diff) => diff.paymentMethodId === item.paymentMethodId)
        ?.difference,
      note: item.note,
    }));

    const incomplete = data.some(
      (item) => item.countedAmount === undefined || item.difference === undefined,
    );
    if (incomplete) {
      toast('Por favor, complete los campos requeridos', 'error');
      return;
    }

    setIsClosing(true);
    cashRegisterService()
      .closeCashRegister(cashSessionId, data)
      .then(() => {
        toast('Caja cerrada correctamente', 'success');
        navigate('/cashbox');
      })
      .catch(() => toast('No se pudo cerrar la caja', 'error'))
      .finally(() => setIsClosing(false));
  };

  useEffect(() => {
    if (id === undefined) {
      return;
    }

    let cancelled = false;
    let summaryRequested = false;

    const loadSummary = (sessionId: string) => {
      summaryRequested = true;
      setCashSessionId(sessionId);
      gridRef.current?.showLoading('Cargando datos...');
      httpClient
        .get<CashClosureRow[]>(API + 'cash-sessions/' + sessionId + '/summary')
        .then((response) => {
          if (!cancelled) {
            gridRef.current?.setRows(response.data);
          }
        })
        .catch(() => toast('No se pudo cargar el resumen de la caja', 'error'))
        .finally(() => {
          if (!cancelled) {
            gridRef.current?.hideLoading();
          }
        });
    };

    gridRef.current?.showLoading('Cargando datos...');

    if (!isLegacyCashRegisterRoute) {
      loadSummary(id);
      return () => {
        cancelled = true;
      };
    }

    queueMicrotask(() => setCashSessionId(null));

    cashboxService
      .getDashboard()
      .then((response) => {
        if (cancelled) {
          return;
        }

        const cashRegister = response.data.find((item) => item.id === Number(id));
        const sessionId = cashRegister?.cashSessionId ?? cashRegister?.cashShiftId;
        if (sessionId === null || sessionId === undefined) {
          toast('No hay una sesión abierta para cerrar esta caja', 'error');
          navigate('/cashbox');
          return;
        }

        loadSummary(String(sessionId));
      })
      .catch(() => toast('No se pudo cargar el resumen de la caja', 'error'))
      .finally(() => {
        if (!cancelled && !summaryRequested) {
          gridRef.current?.hideLoading();
        }
      });

    return () => {
      cancelled = true;
    };
  }, [id, isLegacyCashRegisterRoute, navigate]);

  return (
    <div className="cash-close-page">
      <CashClosureGrid
        ref={gridRef}
        closing={isClosing}
        onCloseRequest={() => setShowCloseConfirm(true)}
      />
      <ConfirmDialog
        open={showCloseConfirm}
        title="Cerrar caja"
        message="¿Está seguro que desea cerrar la caja?"
        cancelLabel="No"
        confirmLabel="Sí"
        onCancel={() => setShowCloseConfirm(false)}
        onConfirm={() => {
          setShowCloseConfirm(false);
          closeCashRegister();
        }}
      />
    </div>
  );
};
