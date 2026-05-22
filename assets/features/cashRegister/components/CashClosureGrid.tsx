import { forwardRef, useImperativeHandle, useRef, useState } from 'react';
import NumberUtils from '@shared/lib/NumberUtils';

export type CashClosureRow = {
  id?: string | number;
  paymentMethodId: number;
  paymentMethod?: string;
  expectedAmount: number | string;
  countedAmount?: number;
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
}

type CashClosureGridProps = {
  closing?: boolean;
  onCloseRequest: () => void;
};

function hasCounted(row: CashClosureRow): boolean {
  return row.countedAmount != null && !Number.isNaN(row.countedAmount);
}

function rowDifference(row: CashClosureRow): number | null {
  if (!hasCounted(row)) return null;
  return Number(row.countedAmount) - Number(row.expectedAmount ?? 0);
}

function diffClassName(diff: number | null): string {
  if (diff == null) return 'cash-close__diff cash-close__diff--empty';
  if (diff === 0) return 'cash-close__diff';
  return diff < 0
    ? 'cash-close__diff cash-close__diff--negative'
    : 'cash-close__diff cash-close__diff--positive';
}

function formatDiff(diff: number): string {
  return `${diff > 0 ? '+' : ''}${NumberUtils.formatCurrency(diff)}`;
}

export const CashClosureGrid = forwardRef<CashClosureGridHandle, CashClosureGridProps>(
  function CashClosureGrid({ closing, onCloseRequest }, ref) {
    const [rows, setRowsState] = useState<CashClosureRow[]>([]);
    const [loading, setLoading] = useState(false);
    const [loadingMessage, setLoadingMessage] = useState('');
    const rowsRef = useRef<CashClosureRow[]>([]);

    useImperativeHandle(ref, () => ({
      showLoading: (message) => {
        setLoadingMessage(message ?? '');
        setLoading(true);
      },
      hideLoading: () => setLoading(false),
      setRows: (newRows) => {
        rowsRef.current = newRows.map((r) => ({ ...r }));
        setRowsState([...rowsRef.current]);
      },
      getRows: () => rowsRef.current,
      getDifferences: () =>
        rowsRef.current.map((row) => ({
          paymentMethodId: row.paymentMethodId,
          difference: Number(row.countedAmount ?? 0) - Number(row.expectedAmount ?? 0),
        })),
    }));

    const updateRow = (index: number, field: keyof CashClosureRow, value: unknown) => {
      rowsRef.current = rowsRef.current.map((r, i) =>
        i === index ? { ...r, [field]: value, updated: true } : r,
      );
      setRowsState([...rowsRef.current]);
    };

    const totalExpected = rows.reduce((sum, r) => sum + Number(r.expectedAmount ?? 0), 0);
    const totalCounted = rows.reduce(
      (sum, r) => sum + (hasCounted(r) ? Number(r.countedAmount) : 0),
      0,
    );
    const totalDiff = totalCounted - totalExpected;
    const anyCounted = rows.some(hasCounted);

    return (
      <section className="cash-close">
        <header className="cash-close__header">
          <div>
            <h1 className="cash-close__title">Cierre de caja</h1>
            <p className="cash-close__subtitle">
              Cuenta el dinero disponible y registra el monto por cada forma de pago.
            </p>
          </div>
          <button
            type="button"
            className="app-button app-button--primary"
            onClick={onCloseRequest}
            disabled={closing || rows.length === 0}
            data-testid="cash-close-btn"
          >
            {closing && <span className="app-button-spinner" aria-hidden="true" />}
            {closing ? 'Cerrando...' : 'Cerrar caja'}
          </button>
        </header>

        <div className="cash-close__panel">
          {loading ? (
            <div className="cash-close__state">{loadingMessage || 'Cargando...'}</div>
          ) : rows.length === 0 ? (
            <div className="cash-close__state">No hay formas de pago para cerrar.</div>
          ) : (
            <table className="cash-close__table">
              <thead>
                <tr>
                  <th>Forma de pago</th>
                  <th className="cash-close__num">Monto esperado</th>
                  <th className="cash-close__num">Monto contado</th>
                  <th className="cash-close__num">Diferencia</th>
                  <th>Nota</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row, i) => {
                  const diff = rowDifference(row);
                  return (
                    <tr key={row.id ?? i}>
                      <td className="cash-close__method">{row.paymentMethod}</td>
                      <td className="cash-close__num">
                        {NumberUtils.formatCurrency(Number(row.expectedAmount ?? 0))}
                      </td>
                      <td className="cash-close__num">
                        <input
                          className="cash-close__input cash-close__input--num"
                          type="number"
                          step="0.01"
                          inputMode="decimal"
                          placeholder="0.00"
                          defaultValue={row.countedAmount}
                          onChange={(e) =>
                            updateRow(
                              i,
                              'countedAmount',
                              e.target.value === '' ? undefined : parseFloat(e.target.value),
                            )
                          }
                        />
                        {row.paymentMethodId !== 1 && (
                          <span className="cash-close__hint">
                            Ingresa el total de tu terminal o app
                          </span>
                        )}
                      </td>
                      <td className="cash-close__num">
                        <span className={diffClassName(diff)}>
                          {diff != null ? formatDiff(diff) : '—'}
                        </span>
                      </td>
                      <td>
                        <input
                          className="cash-close__input"
                          type="text"
                          placeholder="Opcional"
                          defaultValue={row.note ?? ''}
                          onBlur={(e) => updateRow(i, 'note', e.target.value)}
                        />
                      </td>
                    </tr>
                  );
                })}
              </tbody>
              <tfoot>
                <tr>
                  <td className="cash-close__total-label">Total</td>
                  <td className="cash-close__num cash-close__total-value">
                    {NumberUtils.formatCurrency(totalExpected)}
                  </td>
                  <td className="cash-close__num cash-close__total-value">
                    {anyCounted ? NumberUtils.formatCurrency(totalCounted) : '—'}
                  </td>
                  <td className="cash-close__num">
                    <span className={diffClassName(anyCounted ? totalDiff : null)}>
                      {anyCounted ? formatDiff(totalDiff) : '—'}
                    </span>
                  </td>
                  <td />
                </tr>
              </tfoot>
            </table>
          )}
        </div>
      </section>
    );
  },
);
