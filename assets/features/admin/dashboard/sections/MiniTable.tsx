import { Badge, type BadgeProps } from '@nubitio/ui';

import { formatMoney } from '@shared/lib/displayFormat';
import { decimalFormatter } from '@shared/ui/dashboard/formatters';

export type MiniTableColumn<Row> = {
  key: keyof Row & string;
  label: string;
  align?: 'left' | 'right';
  format?: 'currency' | 'number' | 'text';
  /** Render the cell value as a coloured Badge, mapping value → variant. */
  badge?: Record<string, BadgeProps['variant']>;
};

type MiniTableProps<Row extends Record<string, unknown>> = {
  columns: Array<MiniTableColumn<Row>>;
  rows: Row[];
};

function renderCell<Row extends Record<string, unknown>>(col: MiniTableColumn<Row>, row: Row) {
  const raw = row[col.key];

  if (col.badge) {
    const key = String(raw ?? '');
    return (
      <Badge variant={col.badge[key] ?? 'light'} size="sm" pill>
        {key || '—'}
      </Badge>
    );
  }

  if (col.format === 'currency') return formatMoney(raw as number);
  if (col.format === 'number') return decimalFormatter(Number(raw ?? 0));
  return String(raw ?? '—');
}

/** Compact, borderless data table for dashboard breakdown widgets. */
export function MiniTable<Row extends Record<string, unknown>>({
  columns,
  rows,
}: MiniTableProps<Row>) {
  return (
    <div className="efact-mini-table__wrap">
      <table className="efact-mini-table">
        <thead>
          <tr>
            {columns.map((col) => (
              <th key={col.key} className={col.align === 'right' ? 'is-right' : undefined}>
                {col.label}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((row, index) => (
            <tr key={index}>
              {columns.map((col) => (
                <td key={col.key} className={col.align === 'right' ? 'is-right' : undefined}>
                  {renderCell(col, row)}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
