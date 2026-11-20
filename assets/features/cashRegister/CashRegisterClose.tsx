import { useEffect, useRef, useState } from 'react';
import { httpClient } from '@shared/api/HttpClient';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { useLocation, useNavigate, useParams } from 'react-router-dom';
import toast from '@shared/lib/SharedUtils';
import cashRegisterService, { type XReportDto } from './services/cashRegisterService';
import { useSessionInfo } from '@shared/sessionInfo';
import { hasPermission } from '@shared/permissions';
import { cashboxService } from './services/cashboxService';
import { Alert, ConfirmDialog } from '@nubitio/ui';
import NumberUtils from '@shared/lib/NumberUtils';
import { closeConfirmation } from './components/closeConfirmation';
import {
  CashClosureGrid,
  type CashClosureGridHandle,
  type CashClosureRow,
} from './components/CashClosureGrid';
import './CashRegisterClose.scss';

const API = getCoreApiBaseUrl();

/** The server answered but refused to close: its message is what the user needs to read. */
class CloseRejectedError extends Error {}

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
  const { currentUserId, currentBranchId, currentBranchName } = useSessionInfo();
  const [report, setReport] = useState<XReportDto | null>(null);
  const [summaryError, setSummaryError] = useState<string | null>(null);
  const [closeError, setCloseError] = useState<string | null>(null);
  const [attempt, setAttempt] = useState(0);
  const [sessionContext, setSessionContext] = useState('');
  const [confirmation, setConfirmation] = useState(() =>
    closeConfirmation([], [], (value) => String(value)),
  );
  const draftKey = cashSessionId
    ? `nubit:cash-count:${currentUserId}:${currentBranchId}:${cashSessionId}`
    : undefined;
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
      .then((response) => {
        const result = response.data as { success?: boolean; message?: string };
        if (result?.success === false)
          throw new CloseRejectedError(result.message || 'No se pudo cerrar la caja.');
        gridRef.current?.clearDraft();
        toast('Caja cerrada correctamente', 'success');
        navigate('/cashbox/dashboard');
      })
      .catch((error: unknown) =>
        setCloseError(
          error instanceof CloseRejectedError
            ? `${error.message} El conteo se conserva para que puedas reintentar.`
            : 'No se pudo cerrar la caja. El conteo se conserva para que puedas reintentar.',
        ),
      )
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
      setSummaryError(null);
      setReport(null);
      Promise.allSettled([
        httpClient.get<CashClosureRow[]>(API + 'cash-sessions/' + sessionId + '/summary', {
          errorPresentation: 'inline',
        }),
        cashRegisterService().getXReport(sessionId),
        cashboxService.getDashboard(),
      ])
        .then(([summary, xReport, dashboard]) => {
          if (cancelled) return;
          if (summary.status === 'fulfilled') gridRef.current?.setRows(summary.value.data);
          else setSummaryError('No se pudo cargar el resumen de cierre.');
          if (xReport.status === 'fulfilled') setReport(xReport.value.data);
          if (dashboard.status === 'fulfilled') {
            const session = dashboard.value.data.find(
              (item) => String(item.cashSessionId ?? item.cashShiftId) === sessionId,
            );
            setSessionContext(
              [
                session?.name,
                currentBranchName,
                session?.cashier,
                session?.openedAt
                  ? `Abierta ${new Date(session.openedAt).toLocaleString('es-PE')}`
                  : null,
              ]
                .filter(Boolean)
                .join(' · '),
            );
          }
        })
        .finally(() => {
          if (!cancelled) gridRef.current?.hideLoading();
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
  }, [id, isLegacyCashRegisterRoute, navigate, attempt, currentBranchName]);

  return (
    <div className="cash-close-page">
      {closeError && <Alert tone="danger">{closeError}</Alert>}
      <CashClosureGrid
        ref={gridRef}
        report={report}
        context={sessionContext}
        draftKey={draftKey}
        canClose={hasPermission('cash.close')}
        error={summaryError}
        onRetry={() => setAttempt((value) => value + 1)}
        onContinue={() => navigate('/pos')}
        closing={isClosing}
        onCloseRequest={() => {
          setCloseError(null);
          setConfirmation(
            closeConfirmation(
              gridRef.current?.getRows() ?? [],
              gridRef.current?.getDifferences() ?? [],
              (value) => NumberUtils.formatCurrency(value, 'PEN'),
            ),
          );
          setShowCloseConfirm(true);
        }}
      />
      <ConfirmDialog
        open={showCloseConfirm}
        title="Cerrar caja"
        message={confirmation.message}
        variant={confirmation.hasDifferences ? 'warning' : 'info'}
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
