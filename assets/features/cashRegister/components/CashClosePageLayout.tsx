import type { ReactNode } from 'react';
import { Alert, Button } from '@nubitio/ui';
import NumberUtils from '@shared/lib/NumberUtils';
import type { XReportDto } from '../services/cashRegisterService';
import { formatSignedMoney } from '../model/format';
import { CASH_DENOMINATIONS, CASH_METHOD_ID } from './cashCountModel';
import type { CashClosureRow } from './CashClosureGrid';

const money = (value: string | number) => NumberUtils.formatCurrency(Number(value));

function DifferenceText({ value }: { value: number | null }) {
  if (value === null) return <span className="cash-close__muted">—</span>;
  if (value === 0) return <span className="cash-close__ok">Coincide</span>;
  return (
    <span className="cash-close__bad">
      {value < 0 ? 'Faltante' : 'Sobrante'} {formatSignedMoney(value)}
    </span>
  );
}

export interface CashClosePageLayoutProps {
  header: { title: string; breadcrumb: ReactNode; subtitle: string; onCancel: () => void };
  rows: CashClosureRow[];
  report: XReportDto | null | undefined;
  denominations: Record<string, string>;
  mode: 'denominations' | 'amount';
  disabled: boolean;
  canClose: boolean;
  closing: boolean;
  loading: boolean;
  loadingMessage: string;
  error: string | null | undefined;
  draftMessage: string | null;
  draftError: boolean;
  canSaveDraft: boolean;
  ready: boolean;
  reason: string;
  keepFund: boolean;
  differenceOf: (row: CashClosureRow) => number | null;
  onMode: (mode: 'denominations' | 'amount') => void;
  onDenomination: (denomination: number, value: string) => void;
  onCount: (row: CashClosureRow, value: string) => void;
  onReason: (value: string) => void;
  onKeepFund: (value: boolean) => void;
  onSaveDraft: () => void;
  onCloseRequest: () => void;
  onRetry?: () => void;
  onViewSales?: () => void;
}

/**
 * The full-page closing: count the drawer, and see on the side what the
 * system expects and how far the count is from it. The POS dialog uses the older, narrower layout.
 */
export function CashClosePageLayout(props: CashClosePageLayoutProps) {
  const { rows, report, denominations, mode, disabled, differenceOf } = props;
  const cash = rows.find((row) => row.paymentMethodId === CASH_METHOD_ID);
  const cashDifference = cash ? differenceOf(cash) : null;
  const total = cashDifference ?? 0;
  const hasDifference = total !== 0;

  const opening = Number(report?.openingBalance ?? 0);
  const withdrawals = Number(report?.withdrawalsOut ?? 0);
  const expenses = Number(report?.cashAdjustmentsOut ?? 0) - withdrawals;
  const counted = cash?.countedAmount != null ? Number(cash.countedAmount) : null;
  const toSafe = counted === null ? null : Math.max(0, counted - (props.keepFund ? opening : 0));

  const subtotal = (group: readonly number[]) =>
    group.reduce((sum, d) => sum + (d / 100) * Number(denominations[d] || 0), 0);

  return (
    <section className="cash-close cash-close--page" data-testid="cash-close-page">
      <header className="cash-close__header">
        <div>
          {props.header.breadcrumb}
          <h1 className="cash-close__title">
            {props.header.title}
            <span className="cash-close__status">Arqueo en curso</span>
          </h1>
          <p className="cash-close__subtitle">{props.header.subtitle}</p>
        </div>
        <div className="cash-close__header-actions">
          <Button variant="secondary" onClick={props.header.onCancel} disabled={props.closing}>
            Cancelar
          </Button>
          {props.canClose && props.canSaveDraft && (
            <Button
              variant="secondary"
              onClick={props.onSaveDraft}
              disabled={props.closing || props.loading || Boolean(props.error)}
            >
              Guardar conteo
            </Button>
          )}
        </div>
      </header>

      {props.draftMessage && (
        <Alert tone={props.draftError ? 'warning' : 'info'}>{props.draftMessage}</Alert>
      )}
      {props.error ? (
        <Alert tone="warning">
          {props.error}{' '}
          {props.onRetry && <Button onClick={props.onRetry}>Reintentar resumen</Button>}
        </Alert>
      ) : props.loading ? (
        <p role="status">{props.loadingMessage || 'Cargando resumen de caja…'}</p>
      ) : null}

      <div className="cash-close__columns">
        <div className="cash-close__main">
          {cash && (
            <section className="cash-card">
              <header className="cash-card__header">
                <div>
                  <h2>1. Conteo de efectivo</h2>
                  <p>
                    Cuenta los billetes y monedas de la caja e ingresa la cantidad de cada
                    denominación.
                  </p>
                </div>
                <span className="cash-close__tag">Entra al arqueo</span>
              </header>
              {mode === 'denominations' ? (
                <table className="cash-close__count-table">
                  <thead>
                    <tr>
                      <th scope="col">Denominación</th>
                      <th scope="col">Cantidad</th>
                      <th scope="col">Subtotal</th>
                    </tr>
                  </thead>
                  {[CASH_DENOMINATIONS.slice(0, 5), CASH_DENOMINATIONS.slice(5)].map(
                    (group, index) => (
                      <tbody key={index}>
                        <tr className="cash-close__count-group">
                          <th colSpan={3} scope="colgroup">
                            {index === 0 ? 'Billetes' : 'Monedas'}
                          </th>
                        </tr>
                        {group.map((denomination) => (
                          <tr key={denomination}>
                            <th scope="row" className="cash-close__mono">
                              {money(denomination / 100)}
                            </th>
                            <td>
                              <input
                                aria-label={`Cantidad de ${money(denomination / 100)}`}
                                type="number"
                                min="0"
                                max="999999"
                                step="1"
                                inputMode="numeric"
                                placeholder="0"
                                value={denominations[denomination] ?? ''}
                                disabled={disabled || !props.canClose}
                                onChange={(event) =>
                                  props.onDenomination(denomination, event.target.value)
                                }
                              />
                            </td>
                            <td className="cash-close__count-subtotal">
                              {money(
                                (denomination / 100) * Number(denominations[denomination] || 0),
                              )}
                            </td>
                          </tr>
                        ))}
                        <tr className="cash-close__count-sum">
                          <th colSpan={2} scope="row">
                            {index === 0 ? 'Subtotal billetes' : 'Subtotal monedas'}
                          </th>
                          <td>{money(subtotal(group))}</td>
                        </tr>
                      </tbody>
                    ),
                  )}
                </table>
              ) : (
                <label className="cash-close__total-input">
                  Total contado en efectivo
                  <input
                    className="cash-close__input cash-close__input--num"
                    type="number"
                    min="0"
                    step="0.01"
                    inputMode="decimal"
                    placeholder="0.00"
                    aria-label="Monto contado de Efectivo"
                    value={cash.countedAmount ?? ''}
                    disabled={disabled || !props.canClose}
                    onChange={(event) => props.onCount(cash, event.target.value)}
                  />
                </label>
              )}
              <footer className="cash-close__count-total">
                <span>Total contado</span>
                <strong data-testid="cash-counted-total">
                  {counted === null ? 'Pendiente' : money(counted)}
                </strong>
              </footer>
              <button
                type="button"
                className="cash-close__link"
                onClick={() => props.onMode(mode === 'denominations' ? 'amount' : 'denominations')}
              >
                {mode === 'denominations'
                  ? 'Prefiero ingresar solo el total'
                  : 'Contar por billetes y monedas'}
              </button>
            </section>
          )}
        </div>

        <aside className="cash-close__aside">
          <section className="cash-card">
            <h2>Efectivo esperado según el sistema</h2>
            <dl className="cash-close__lines">
              {report && (
                <>
                  <div>
                    <dt>Fondo inicial</dt>
                    <dd>{money(opening)}</dd>
                  </div>
                  {report.cashSalesNet != null && (
                    <div>
                      <dt>Ventas en efectivo</dt>
                      <dd>{money(report.cashSalesNet)}</dd>
                    </div>
                  )}
                  <div>
                    <dt>Ingresos</dt>
                    <dd>{money(report.cashIn)}</dd>
                  </div>
                  {report.cashAdjustmentsOut != null && (
                    <>
                      <div>
                        <dt>Egresos</dt>
                        <dd className={expenses > 0 ? 'cash-close__bad' : undefined}>
                          {formatSignedMoney(-expenses)}
                        </dd>
                      </div>
                      <div>
                        <dt>Retiros de efectivo</dt>
                        <dd className={withdrawals > 0 ? 'cash-close__bad' : undefined}>
                          {formatSignedMoney(-withdrawals)}
                        </dd>
                      </div>
                    </>
                  )}
                </>
              )}
              <div className="is-total">
                <dt>Efectivo esperado</dt>
                <dd>{cash ? money(cash.expectedAmount) : 'Sin datos'}</dd>
              </div>
            </dl>
          </section>

          <section className="cash-card">
            <h2>Conciliación del cierre</h2>
            <dl className="cash-close__lines">
              <div>
                <dt>Efectivo contado</dt>
                <dd className="is-strong">{counted === null ? '—' : money(counted)}</dd>
              </div>
              <div>
                <dt>Diferencia en efectivo</dt>
                <dd>
                  <DifferenceText value={cashDifference} />
                </dd>
              </div>
            </dl>
            <div className="cash-close__reconcile">
              <span className="cash-close__reconcile-label">Diferencia total</span>
              <div
                className={`cash-close__difference${total < 0 ? ' is-negative' : total > 0 ? ' is-positive' : ''}`}
                role="status"
              >
                <span>{total < 0 ? 'Faltante' : total > 0 ? 'Sobrante' : 'Sin diferencia'}</span>
                <strong>{formatSignedMoney(total)}</strong>
              </div>
            </div>
            {hasDifference && (
              <label className="cash-close__field">
                <span>
                  Motivo de la diferencia <abbr title="Obligatorio">*</abbr>
                </span>
                <textarea
                  className="cash-close__input cash-close__textarea"
                  aria-label="Motivo de la diferencia"
                  value={props.reason}
                  disabled={disabled || !props.canClose}
                  required
                  rows={3}
                  placeholder="Describe qué pasó, por ejemplo un vuelto mal entregado"
                  onChange={(event) => props.onReason(event.target.value)}
                />
                <small className="cash-close__hint">Obligatorio para cerrar con diferencia.</small>
              </label>
            )}
            <div className="cash-close__destination">
              <label className="cash-close__check">
                <input
                  type="checkbox"
                  checked={props.keepFund}
                  disabled={disabled || !props.canClose}
                  onChange={(event) => props.onKeepFund(event.target.checked)}
                />
                Dejar {money(opening)} como fondo del siguiente turno
              </label>
              <div className="cash-close__line">
                <span>Efectivo a retirar de la caja</span>
                <strong>{toSafe === null ? '—' : money(toSafe)}</strong>
              </div>
            </div>
            {props.canClose && (
              <Button
                variant="primary"
                icon="ph ph-lock-simple"
                fullWidth
                loading={props.closing}
                disabled={!props.ready}
                onClick={props.onCloseRequest}
                data-testid="cash-close-btn"
              >
                Cerrar turno
              </Button>
            )}
            <p className="cash-close__note">
              Al cerrar se emite el reporte de cierre y el turno queda bloqueado.
            </p>
            {props.onViewSales && (
              <button
                type="button"
                className="cash-close__link"
                data-testid="cash-close-view-sales"
                onClick={props.onViewSales}
              >
                Ver ventas del turno
              </button>
            )}
          </section>
        </aside>
      </div>
    </section>
  );
}
