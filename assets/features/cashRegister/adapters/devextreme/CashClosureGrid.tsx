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
  onCloseRequest: () => void;
};

export const CashClosureGrid = forwardRef<CashClosureGridHandle, CashClosureGridProps>(
  function CashClosureGrid({ onCloseRequest }, ref) {
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

    const getDiff = (row: CashClosureRow): number | null => {
      if (row.countedAmount == null) return null;
      return Number(row.countedAmount) - Number(row.expectedAmount ?? 0);
    };

    const rowClass = (row: CashClosureRow): string => {
      const diff = getDiff(row);
      if (diff == null || diff === 0) return '';
      return diff < 0 ? 'row-diff-negative' : 'row-diff-positive';
    };

    return (
      <div className="grid theme-dependent" style={{ height: '100%', display: 'flex', flexDirection: 'column' }}>
        {/* Toolbar */}
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '10px 0' }}>
          <div className="grid-header">Cierre de caja</div>
          <button
            type="button"
            className="app-button app-button--primary app-button--sm"
            onClick={onCloseRequest}
            data-testid="cash-close-btn"
          >
            Cerrar caja
          </button>
        </div>

        {/* Loading overlay */}
        {loading && (
          <div style={{ padding: '8px', textAlign: 'center', color: 'var(--texteditor-label-color)' }}>
            {loadingMessage || 'Cargando…'}
          </div>
        )}

        {/* Table */}
        <div style={{ flex: 1, overflowY: 'auto' }}>
          <table style={{ width: '100%', borderCollapse: 'collapse' }}>
            <thead>
              <tr>
                <th style={{ textAlign: 'left', padding: '6px 8px', borderBottom: '1px solid var(--base-border-color)' }}>Forma de pago</th>
                <th style={{ textAlign: 'right', padding: '6px 8px', borderBottom: '1px solid var(--base-border-color)' }}>Monto esperado</th>
                <th style={{ textAlign: 'right', padding: '6px 8px', borderBottom: '1px solid var(--base-border-color)', minWidth: 160 }}>Monto contado</th>
                <th style={{ textAlign: 'right', padding: '6px 8px', borderBottom: '1px solid var(--base-border-color)' }}>Diferencia</th>
                <th style={{ textAlign: 'left', padding: '6px 8px', borderBottom: '1px solid var(--base-border-color)', minWidth: 300 }}>Nota</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row, i) => {
                const diff = getDiff(row);
                return (
                  <tr key={row.id ?? i} className={rowClass(row)}>
                    <td style={{ padding: '6px 8px', borderBottom: '1px solid var(--base-border-color)' }}>
                      {row.paymentMethod}
                    </td>
                    <td style={{ padding: '6px 8px', borderBottom: '1px solid var(--base-border-color)', textAlign: 'right' }}>
                      {NumberUtils.formatCurrency(Number(row.expectedAmount ?? 0))}
                    </td>
                    <td style={{ padding: '6px 8px', borderBottom: '1px solid var(--base-border-color)' }} className="col-counted-amount">
                      <div>
                        <input
                          type="number"
                          step="0.01"
                          defaultValue={row.countedAmount}
                          onBlur={(e) => updateRow(i, 'countedAmount', parseFloat(e.target.value))}
                          style={{ width: '100%', textAlign: 'right' }}
                        />
                        {row.paymentMethodId !== 1 && (
                          <small style={{ color: 'var(--texteditor-label-color, #888)', fontSize: '11px' }}>
                            Ingresa el total de tu terminal o app
                          </small>
                        )}
                      </div>
                    </td>
                    <td style={{ padding: '6px 8px', borderBottom: '1px solid var(--base-border-color)', textAlign: 'right' }}>
                      {diff != null ? NumberUtils.formatCurrency(diff) : ''}
                    </td>
                    <td style={{ padding: '6px 8px', borderBottom: '1px solid var(--base-border-color)' }}>
                      <input
                        type="text"
                        defaultValue={row.note ?? ''}
                        onBlur={(e) => updateRow(i, 'note', e.target.value)}
                        style={{ width: '100%' }}
                      />
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>
    );
  },
);
