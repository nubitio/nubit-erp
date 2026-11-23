import { useEffect, useRef, useState } from 'react';
import { Alert, ConfirmDialog } from '@nubitio/ui';
import { httpClient } from '@shared/api/HttpClient';
import { getCoreApiBaseUrl } from '@nubitio/core';
import toast from '@shared/lib/SharedUtils';
import NumberUtils from '@shared/lib/NumberUtils';
import { useSessionInfo } from '@shared/sessionInfo';
import { hasPermission } from '@shared/permissions';
import cashRegisterService, { type XReportDto } from '../services/cashRegisterService';
import { cashboxService } from '../services/cashboxService';
import { closeConfirmation } from './closeConfirmation';
import {
  CashClosureGrid,
  type CashClosureGridHandle,
  type CashClosureRow,
} from './CashClosureGrid';
import '../CashRegisterClose.scss';

const API = getCoreApiBaseUrl();

/** The server answered but refused to close: its message is what the user needs to read. */
class CloseRejectedError extends Error {}

interface Props {
  /** Give the component `key={sessionId}`: a different session starts from a clean count. */
  sessionId: string;
  /** Inside a dialog: the dialog supplies the title and the page chrome (print, padding) is dropped. */
  embedded?: boolean;
  /** The server closed the session. */
  onClosed: () => void;
  /** The count was saved to this device and the cashier goes back to selling. */
  onContinue: () => void;
  /** Only where the signed-in user owns the session: the list shows their own shift. */
  onViewSales?: () => void;
}

/**
 * Count the drawer and close a cash session. One flow for the full page (`/cash-session/:id/close`)
 * and for the dialog inside the POS, so the count, the draft and the confirmation never diverge.
 */
export function CashCloseFlow({
  sessionId,
  embedded = false,
  onClosed,
  onContinue,
  onViewSales,
}: Props) {
  const gridRef = useRef<CashClosureGridHandle | null>(null);
  const [showCloseConfirm, setShowCloseConfirm] = useState(false);
  const [isClosing, setIsClosing] = useState(false);
  const { currentUserId, currentBranchId, currentBranchName } = useSessionInfo();
  const [report, setReport] = useState<XReportDto | null>(null);
  const [summaryError, setSummaryError] = useState<string | null>(null);
  const [closeError, setCloseError] = useState<string | null>(null);
  const [attempt, setAttempt] = useState(0);
  const [sessionContext, setSessionContext] = useState('');
  const [confirmation, setConfirmation] = useState(() =>
    closeConfirmation([], [], (value) => String(value)),
  );
  const draftKey = `nubit:cash-count:${currentUserId}:${currentBranchId}:${sessionId}`;

  const closeCashRegister = () => {
    const closureData = gridRef.current?.getRows();
    const differences = gridRef.current?.getDifferences() ?? [];
    if (closureData === undefined) {
      toast('Error al cerrar la caja', 'error');
      return;
    }

    const data = closureData.map((item) => ({
      cashSession: API + 'cash-sessions/' + sessionId,
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
      .closeCashRegister(sessionId, data)
      .then((response) => {
        const result = response.data as { success?: boolean; message?: string };
        if (result?.success === false)
          throw new CloseRejectedError(result.message || 'No se pudo cerrar la caja.');
        gridRef.current?.clearDraft();
        toast('Caja cerrada correctamente', 'success');
        onClosed();
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
    let cancelled = false;
    gridRef.current?.showLoading('Cargando datos...');

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

    return () => {
      cancelled = true;
    };
  }, [sessionId, attempt, currentBranchName]);

  return (
    <div className={embedded ? 'cash-close-embedded' : 'cash-close-page'}>
      {closeError && <Alert tone="danger">{closeError}</Alert>}
      <CashClosureGrid
        ref={gridRef}
        embedded={embedded}
        report={report}
        context={sessionContext}
        draftKey={draftKey}
        canClose={hasPermission('cash.close')}
        error={summaryError}
        onRetry={() => {
          setSummaryError(null);
          setReport(null);
          setAttempt((value) => value + 1);
        }}
        onContinue={onContinue}
        onViewSales={onViewSales}
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
}
