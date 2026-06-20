import { forwardRef, useImperativeHandle, useRef, useState } from 'react';
import NumberUtils from '@shared/lib/NumberUtils';

export type PriceEditorRow = {
  id: string | number;
  name?: string;
  /** Effective price as decimal string (from API) or number (after user edits). */
  price?: string | number;
  priceSource?: 'override' | 'multiplier' | 'base';
  updated?: boolean;
};

export interface PriceEditorGridHandle {
  showLoading: (message?: string) => void;
  hideLoading: () => void;
  setRows: (rows: PriceEditorRow[]) => void;
  getUpdatedRows: () => PriceEditorRow[];
}

const SOURCE_BADGE: Record<string, { label: string; color: string }> = {
  override: { label: 'Precio especial', color: 'var(--color-primary, #7c3aed)' },
  multiplier: { label: 'Multiplicador', color: 'var(--color-info, #0891b2)' },
  base: { label: 'Precio base', color: 'var(--texteditor-label-color, #6b7280)' },
};

export const PriceEditorGrid = forwardRef<PriceEditorGridHandle>(function PriceEditorGrid(
  _props,
  ref,
) {
  const [rows, setRowsState] = useState<PriceEditorRow[]>([]);
  const [loading, setLoading] = useState(false);
  const [loadingMessage, setLoadingMessage] = useState('');
  const rowsRef = useRef<PriceEditorRow[]>([]);

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
    getUpdatedRows: () => rowsRef.current.filter((r) => r.updated),
  }));

  const updatePrice = (index: number, value: string) => {
    const parsed = parseFloat(value);
    if (isNaN(parsed) || parsed < 0) return;
    rowsRef.current = rowsRef.current.map((r, i) =>
      i === index
        ? { ...r, price: parsed.toFixed(4), priceSource: 'override', updated: true }
        : r,
    );
    setRowsState([...rowsRef.current]);
  };

  return (
    <div style={{ height: '100%', display: 'flex', flexDirection: 'column' }}>
      {loading && (
        <div style={{ padding: '8px', textAlign: 'center', color: 'var(--texteditor-label-color)' }}>
          {loadingMessage || 'Cargando...'}
        </div>
      )}
      <div style={{ flex: 1, overflowY: 'auto' }}>
        <table style={{ width: '100%', borderCollapse: 'collapse' }}>
          <thead>
            <tr>
              <th
                style={{
                  textAlign: 'left',
                  padding: '6px 8px',
                  borderBottom: '1px solid var(--base-border-color)',
                  minWidth: 300,
                }}
              >
                Producto
              </th>
              <th
                style={{
                  textAlign: 'right',
                  padding: '6px 8px',
                  borderBottom: '1px solid var(--base-border-color)',
                }}
              >
                Precio
              </th>
            </tr>
          </thead>
          <tbody>
            {rows.map((row, i) => {
              const badge = SOURCE_BADGE[row.priceSource ?? 'base'] ?? SOURCE_BADGE['base'];
              return (
                <tr key={row.id}>
                  <td style={{ padding: '6px 8px', borderBottom: '1px solid var(--base-border-color)' }}>
                    {row.name}
                    {row.priceSource && row.priceSource !== 'base' && (
                      <span
                        style={{
                          marginLeft: 6,
                          fontSize: 11,
                          color: badge.color,
                          fontWeight: 500,
                        }}
                        title={badge.label}
                      >
                        ● {badge.label}
                      </span>
                    )}
                  </td>
                  <td
                    style={{
                      padding: '6px 8px',
                      borderBottom: '1px solid var(--base-border-color)',
                      textAlign: 'right',
                    }}
                  >
                    <input
                      type="number"
                      step="0.01"
                      min="0"
                      defaultValue={Number(row.price ?? 0)}
                      onBlur={(e) => updatePrice(i, e.target.value)}
                      style={{ width: '100%', textAlign: 'right' }}
                      aria-label={`Precio de ${row.name ?? 'producto'}`}
                    />
                    <span
                      style={{
                        display: 'block',
                        fontSize: '12px',
                        color: 'var(--texteditor-label-color)',
                      }}
                    >
                      {NumberUtils.formatCurrency(Number(row.price ?? 0))}
                    </span>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    </div>
  );
});
