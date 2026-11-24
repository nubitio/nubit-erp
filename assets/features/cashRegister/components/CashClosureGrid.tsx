import { forwardRef, useImperativeHandle, useRef, useState, type ReactNode } from 'react';
import {
  Alert,
  Badge,
  Button,
  DescriptionList,
  DetailLayout,
  PageHeader,
  Row,
  Section,
} from '@nubitio/ui';
import NumberUtils from '@shared/lib/NumberUtils';
import type { XReportDto } from '../services/cashRegisterService';
import { CashClosePageLayout } from './CashClosePageLayout';
import {
  CASH_DENOMINATIONS,
  CASH_METHOD_ID,
  cashMinor,
  denominationTotal,
  parseCashCount,
} from './cashCountModel';

export type CashClosureRow = {
  id?: string | number;
  paymentMethodId: number;
  paymentMethod?: string;
  expectedAmount: number | string;
  countedAmount?: number | string;
  difference?: number;
  note?: string;
  updated?: boolean;
};
export interface CashClosureGridHandle {
  showLoading: (message?: string) => void;
  hideLoading: () => void;
  setRows: (rows: CashClosureRow[]) => void;
  getRows: () => CashClosureRow[];
  getDifferences: () => { paymentMethodId: number; difference: number }[];
  /** How the cash was counted and whether the opening fund stays: recorded with the closing. */
  getCloseDetails: () => {
    denominations: Record<string, number> | null;
    fundKept: boolean | null;
  };
  clearDraft: () => void;
}
type Props = {
  /** Inside a dialog that already titles the screen: no page header, no print. */
  embedded?: boolean;
  closing?: boolean;
  onCloseRequest: () => void;
  onContinue?: () => void;
  /** Opens the shift's sales; absent when the signed-in user does not own the session. */
  onViewSales?: () => void;
  draftKey?: string;
  report?: XReportDto | null;
  context?: string;
  canClose?: boolean;
  error?: string | null;
  onRetry?: () => void;
  /** The full page's header: the screen's title, the way back, and what "Cancelar" does. */
  pageHeader?: { title: string; breadcrumb: ReactNode; onCancel: () => void };
};
const money = (value: string | number) => NumberUtils.formatCurrency(Number(value));
const difference = (row: CashClosureRow) =>
  row.countedAmount == null
    ? null
    : Number(cashMinor(row.countedAmount) - cashMinor(row.expectedAmount)) / 100;

type Draft = {
  rows: Array<{ paymentMethodId: number; countedAmount?: number | string; note?: string }>;
  denominations: Record<string, string>;
  mode: 'denominations' | 'amount';
  reason?: string;
  keepFund?: boolean;
  savedAt: string;
};
function readDraft(key?: string): Draft | null {
  if (!key) return null;
  try {
    const data = JSON.parse(localStorage.getItem(key) ?? 'null');
    if (!data || !Array.isArray(data.rows)) return null;
    return {
      ...data,
      rows: data.rows.filter(
        (row: unknown) =>
          row &&
          typeof row === 'object' &&
          typeof (row as { paymentMethodId?: unknown }).paymentMethodId === 'number',
      ),
    };
  } catch {
    return null;
  }
}

export const CashClosureGrid = forwardRef<CashClosureGridHandle, Props>(function CashClosureGrid(
  {
    embedded = false,
    closing = false,
    onCloseRequest,
    onContinue,
    onViewSales,
    draftKey,
    report,
    context,
    canClose = true,
    error,
    onRetry,
    pageHeader,
  },
  ref,
) {
  const [rows, setRows] = useState<CashClosureRow[]>([]);
  const rowsRef = useRef<CashClosureRow[]>([]);
  const [loading, setLoading] = useState(false);
  const [loadingMessage, setLoadingMessage] = useState('');
  const [denominations, setDenominations] = useState<Record<string, string>>({});
  const [mode, setMode] = useState<'denominations' | 'amount'>('denominations');
  const [draftMessage, setDraftMessage] = useState<string | null>(null);
  const [draftError, setDraftError] = useState(false);
  // Full page: one reason for the whole difference, and whether the opening fund stays in the drawer.
  const [reason, setReason] = useState('');
  const [keepFund, setKeepFund] = useState(true);
  const reasonRef = useRef('');
  reasonRef.current = reason;
  const keepFundRef = useRef(true);
  keepFundRef.current = keepFund;
  const modeRef = useRef<'denominations' | 'amount'>('denominations');
  modeRef.current = mode;
  const denominationsRef = useRef<Record<string, string>>({});
  denominationsRef.current = denominations;
  function updateRow(id: number, patch: Partial<CashClosureRow>) {
    rowsRef.current = rowsRef.current.map((row) =>
      row.paymentMethodId === id ? { ...row, ...patch, updated: true } : row,
    );
    setRows([...rowsRef.current]);
  }
  useImperativeHandle(ref, () => ({
    showLoading(message) {
      setLoading(true);
      setLoadingMessage(message ?? '');
    },
    hideLoading() {
      setLoading(false);
    },
    setRows(newRows) {
      const draft = readDraft(draftKey);
      // Only the drawer is counted: Yape, cards and transfers are checked against the terminal or the bank.
      rowsRef.current = newRows
        .filter((row) => row.paymentMethodId === CASH_METHOD_ID)
        .map((row) => {
          const saved = draft?.rows.find((item) => item.paymentMethodId === row.paymentMethodId);
          return {
            ...row,
            ...(saved
              ? {
                  countedAmount:
                    saved.countedAmount != null
                      ? parseCashCount(String(saved.countedAmount))
                      : undefined,
                  note: typeof saved.note === 'string' ? saved.note : '',
                }
              : {}),
          };
        });
      setRows([...rowsRef.current]);
      setDenominations(
        draft?.denominations && typeof draft.denominations === 'object' ? draft.denominations : {},
      );
      setMode(draft?.mode === 'amount' ? 'amount' : 'denominations');
      setReason(typeof draft?.reason === 'string' ? draft.reason : '');
      setKeepFund(draft?.keepFund !== false);
      setDraftMessage(draft ? 'Conteo recuperado de este equipo.' : null);
    },
    getRows: () =>
      embedded
        ? rowsRef.current
        : rowsRef.current.map((row) => {
            const gap = difference(row);
            return gap !== null && gap !== 0 ? { ...row, note: reasonRef.current } : row;
          }),
    getCloseDetails: () => {
      const counted: Record<string, number> = {};
      if (modeRef.current === 'denominations') {
        for (const [cents, quantity] of Object.entries(denominationsRef.current)) {
          if (/^\d+$/.test(quantity) && Number(quantity) > 0) counted[cents] = Number(quantity);
        }
      }
      return {
        denominations: Object.keys(counted).length > 0 ? counted : null,
        // Only the full page asks whether the fund stays; the POS dialog does not.
        fundKept: embedded ? null : keepFundRef.current,
      };
    },
    getDifferences: () =>
      rowsRef.current.map((row) => ({
        paymentMethodId: row.paymentMethodId,
        difference: difference(row) ?? 0,
      })),
    clearDraft() {
      try {
        if (draftKey) localStorage.removeItem(draftKey);
      } catch {
        /* A completed server closure is authoritative. */
      }
    },
  }));
  const cash = rows.find((row) => row.paymentMethodId === CASH_METHOD_ID);
  const cashDifference = cash ? difference(cash) : null;
  const allCounted =
    rows.length > 0 &&
    rows.every(
      (row) => row.countedAmount != null && parseCashCount(String(row.countedAmount)) !== undefined,
    );
  const hasGap = rows.some((row) => difference(row) !== null && difference(row) !== 0);
  const missingReasons = embedded
    ? rows.some((row) => difference(row) !== null && difference(row) !== 0 && !row.note?.trim())
    : hasGap && !reason.trim();
  const ready = allCounted && !missingReasons && !loading && !error;
  function changeDenomination(denomination: number, value: string) {
    const next = { ...denominations, [denomination]: value };
    setDenominations(next);
    setMode('denominations');
    const total = denominationTotal(next);
    updateRow(CASH_METHOD_ID, { countedAmount: total });
  }
  function saveDraft() {
    if (!draftKey) return;
    try {
      localStorage.setItem(
        draftKey,
        JSON.stringify({
          rows: rowsRef.current,
          denominations,
          mode,
          reason,
          keepFund,
          savedAt: new Date().toISOString(),
        } satisfies Draft),
      );
      setDraftError(false);
      setDraftMessage('Conteo guardado en este equipo. Puedes retomarlo en esta sesión de caja.');
      // The cashier's dialog goes back to selling; the full page stays where it is.
      if (embedded) onContinue?.();
    } catch {
      setDraftError(true);
      setDraftMessage('No se pudo guardar el conteo en este equipo. Intenta nuevamente.');
    }
  }
  const countInput = (row: CashClosureRow) => (
    <label className="cash-close__field">
      {!embedded && row.paymentMethodId === CASH_METHOD_ID
        ? 'Efectivo contado'
        : `Contado · ${row.paymentMethod}`}
      <input
        className="cash-close__input cash-close__input--num"
        type="number"
        min="0"
        step="0.01"
        inputMode="decimal"
        placeholder="0.00"
        aria-label={`Monto contado de ${row.paymentMethod}`}
        value={row.countedAmount ?? ''}
        disabled={closing || !canClose}
        onChange={(event) => {
          setMode(row.paymentMethodId === CASH_METHOD_ID ? 'amount' : mode);
          updateRow(row.paymentMethodId, { countedAmount: parseCashCount(event.target.value) });
        }}
      />
    </label>
  );
  const reasonInput = (row: CashClosureRow) =>
    difference(row) !== null && difference(row) !== 0 ? (
      <label className="cash-close__field">
        Motivo de la diferencia
        <input
          className="cash-close__input"
          aria-label={`Motivo de la diferencia de ${row.paymentMethod}`}
          value={row.note ?? ''}
          disabled={closing || !canClose}
          required
          onChange={(event) => updateRow(row.paymentMethodId, { note: event.target.value })}
          placeholder="Explica el sobrante o faltante"
        />
      </label>
    ) : null;
  if (!embedded && pageHeader) {
    return (
      <CashClosePageLayout
        header={{ ...pageHeader, subtitle: context || '' }}
        rows={rows}
        report={report}
        denominations={denominations}
        mode={mode}
        disabled={closing}
        canClose={canClose}
        closing={closing}
        loading={loading}
        loadingMessage={loadingMessage}
        error={error}
        draftMessage={draftMessage}
        draftError={draftError}
        canSaveDraft={Boolean(draftKey)}
        ready={ready}
        reason={reason}
        keepFund={keepFund}
        differenceOf={difference}
        onMode={setMode}
        onDenomination={changeDenomination}
        onCount={(row, value) => {
          if (row.paymentMethodId === CASH_METHOD_ID) setMode('amount');
          updateRow(row.paymentMethodId, { countedAmount: parseCashCount(value) });
        }}
        onReason={setReason}
        onKeepFund={setKeepFund}
        onSaveDraft={saveDraft}
        onCloseRequest={onCloseRequest}
        onRetry={onRetry}
        onViewSales={onViewSales}
      />
    );
  }

  return (
    <section className="cash-close">
      {embedded ? (
        <Row gap={3} align="center" wrap>
          <Badge variant="success" pill>
            Turno abierto
          </Badge>
          {context && <span className="cash-close__hint">{context}</span>}
        </Row>
      ) : (
        <PageHeader
          title="Cierre de caja"
          subtitle={context || 'Revisa el turno y cuenta el dinero antes de cerrar.'}
          metadata={
            <Badge variant="success" pill>
              Turno abierto
            </Badge>
          }
          actions={
            <Button
              variant="secondary"
              onClick={() => window.print()}
              disabled={loading || Boolean(error)}
            >
              Imprimir pre-cierre
            </Button>
          }
        />
      )}
      {draftMessage && <Alert tone={draftError ? 'warning' : 'info'}>{draftMessage}</Alert>}
      {error ? (
        <Alert tone="warning">
          {error} {onRetry && <Button onClick={onRetry}>Reintentar resumen</Button>}
        </Alert>
      ) : loading ? (
        <p role="status">{loadingMessage || 'Cargando resumen de caja…'}</p>
      ) : null}
      <DetailLayout
        aside={
          <>
            <Section variant="panel" title="Cuadre de efectivo">
              <DescriptionList
                items={[
                  ...(report ? [{ term: 'Apertura', value: money(report.openingBalance) }] : []),
                  ...(report
                    ? [
                        ...(report.cashSalesNet != null
                          ? [
                              {
                                term: 'Ventas y devoluciones en efectivo',
                                value: money(report.cashSalesNet),
                              },
                            ]
                          : []),
                        { term: 'Ingresos de caja', value: money(report.cashIn) },
                        ...(report.cashAdjustmentsOut != null
                          ? [{ term: 'Egresos de caja', value: money(report.cashAdjustmentsOut) }]
                          : []),
                      ]
                    : []),
                  {
                    term: 'Esperado en efectivo',
                    value: cash ? money(cash.expectedAmount) : 'Sin datos',
                  },
                ]}
              />
              {cash && (
                <>
                  {countInput(cash)}
                  {cashDifference !== null && (
                    <div
                      className={`cash-close__difference${cashDifference < 0 ? ' is-negative' : cashDifference > 0 ? ' is-positive' : ''}`}
                      role="status"
                    >
                      <span>
                        {cashDifference < 0
                          ? 'Faltante'
                          : cashDifference > 0
                            ? 'Sobrante'
                            : 'Efectivo conforme'}
                      </span>
                      <strong>
                        {cashDifference === 0 ? 'Sin diferencia' : money(cashDifference)}
                      </strong>
                    </div>
                  )}
                  {reasonInput(cash)}
                </>
              )}
            </Section>
            <div className="cash-close__actions">
              {canClose && (
                <Button
                  variant="primary"
                  loading={closing}
                  disabled={!ready}
                  onClick={onCloseRequest}
                  data-testid="cash-close-btn"
                >
                  Cerrar caja
                </Button>
              )}
              {canClose && draftKey && (
                <Button
                  variant="secondary"
                  disabled={closing || loading || Boolean(error)}
                  onClick={saveDraft}
                >
                  Guardar conteo y seguir vendiendo
                </Button>
              )}
              {!allCounted ? (
                <p className="cash-close__hint">Completa el conteo de todas las formas de pago.</p>
              ) : missingReasons ? (
                <p className="cash-close__hint">
                  Indica el motivo de las diferencias antes de cerrar.
                </p>
              ) : (
                <p className="cash-close__hint">El cierre se confirmará en el siguiente paso.</p>
              )}
            </div>
          </>
        }
      >
        <Section variant="panel" title="Ventas del turno por método">
          {report ? (
            <>
              <p className="cash-close__sales-total">
                Ventas registradas <strong>{money(report.totalSales)}</strong>
              </p>
              <table className="cash-close__table">
                <thead>
                  <tr>
                    <th>Método</th>
                    <th>Operaciones</th>
                    <th>Monto</th>
                    <th>Dónde se controla</th>
                  </tr>
                </thead>
                <tbody>
                  {report.salesByPaymentMethod.map((row) => (
                    <tr key={row.paymentMethodId}>
                      <th scope="row">{row.paymentMethod}</th>
                      <td>{row.operations ?? '—'}</td>
                      <td>{money(row.amount)}</td>
                      <td>
                        {row.paymentMethodId === CASH_METHOD_ID
                          ? 'Conteo en caja'
                          : 'Terminal, aplicación o banco'}
                      </td>
                    </tr>
                  ))}
                  {Number(report.receivableTotal ?? 0) > 0 && (
                    <tr>
                      <th scope="row">A crédito</th>
                      <td>{report.receivableOperations ?? '—'}</td>
                      <td>{money(report.receivableTotal ?? 0)}</td>
                      <td>Por cobrar: no entra a caja</td>
                    </tr>
                  )}
                  {report.salesByPaymentMethod.length === 0 &&
                    Number(report.receivableTotal ?? 0) <= 0 && (
                      <tr>
                        <td colSpan={4}>Sin ventas registradas en este turno.</td>
                      </tr>
                    )}
                </tbody>
              </table>
              {onViewSales && (
                <Button
                  variant="secondary"
                  onClick={onViewSales}
                  data-testid="cash-close-view-sales"
                >
                  Ver ventas del turno
                </Button>
              )}
            </>
          ) : (
            <p className="cash-close__hint">
              El detalle de ventas no está disponible. El conteo usa el resumen de cierre del
              servidor.
            </p>
          )}
        </Section>
        {cash && (
          <Section variant="panel" title="Conteo de efectivo">
            <div
              className="cash-close__count-mode"
              role="group"
              aria-label="Forma de contar efectivo"
            >
              <Button
                variant={mode === 'denominations' ? 'primary' : 'secondary'}
                aria-pressed={mode === 'denominations'}
                onClick={() => setMode('denominations')}
              >
                Billetes y monedas
              </Button>
              <Button
                variant={mode === 'amount' ? 'primary' : 'secondary'}
                aria-pressed={mode === 'amount'}
                onClick={() => setMode('amount')}
              >
                Ingresar importe
              </Button>
            </div>
            {mode === 'denominations' ? (
              <div className="cash-close__denominations">
                {[CASH_DENOMINATIONS.slice(0, 5), CASH_DENOMINATIONS.slice(5)].map(
                  (group, index) => (
                    <fieldset key={index}>
                      <legend>{index === 0 ? 'Billetes' : 'Monedas'}</legend>
                      {group.map((denomination) => (
                        <label key={denomination}>
                          <span>{money(denomination / 100)}</span>
                          <span aria-hidden="true">×</span>
                          <input
                            aria-label={`Cantidad de ${money(denomination / 100)}`}
                            type="number"
                            min="0"
                            max="999999"
                            step="1"
                            inputMode="numeric"
                            placeholder="0"
                            value={denominations[denomination] ?? ''}
                            disabled={closing || !canClose}
                            onChange={(event) =>
                              changeDenomination(denomination, event.target.value)
                            }
                          />
                        </label>
                      ))}
                    </fieldset>
                  ),
                )}
              </div>
            ) : (
              <p className="cash-close__hint">
                Ingresa el total contado en el panel de cuadre de efectivo.
              </p>
            )}
            <p className="cash-close__count-total">
              Contado{' '}
              <strong>
                {cash.countedAmount == null ? 'Pendiente' : money(cash.countedAmount)}
              </strong>
            </p>
          </Section>
        )}
      </DetailLayout>
    </section>
  );
});
