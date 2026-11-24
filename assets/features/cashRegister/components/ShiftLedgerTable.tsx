import { useState } from 'react';
import { Combobox, DataTable, type DataTableColumn } from '@nubitio/ui';
import NumberUtils from '@shared/lib/NumberUtils';
import type { CashLedgerEntry } from '../services/cashSessionService';
import { formatSignedMoney, formatTime } from '../model/format';
import { countByFilter, filterLedger, KIND_LABEL, type LedgerFilter } from '../model/ledger';

const FILTERS: { value: LedgerFilter; label: string }[] = [
  { value: 'all', label: 'Todos' },
  { value: 'sale', label: 'Ventas' },
  { value: 'income', label: 'Ingresos' },
  { value: 'expense', label: 'Egresos' },
  { value: 'withdrawal', label: 'Retiros' },
];

interface Props {
  entries: CashLedgerEntry[];
  /** Only these filters are offered (the "Ventas" tab shows sales alone). */
  filters?: LedgerFilter[];
  emptyMessage: string;
}

/** The movements of a shift, with a chip per kind and a filter by the way it was paid. */
export function ShiftLedgerTable({ entries, filters, emptyMessage }: Props) {
  const [filter, setFilter] = useState<LedgerFilter>(filters?.[0] ?? 'all');
  const [method, setMethod] = useState<string | null>(null);
  const counts = countByFilter(entries);
  const methods = Array.from(
    new Set(entries.map((entry) => entry.paymentMethod).filter((name): name is string => !!name)),
  ).sort((a, b) => a.localeCompare(b));
  const visible = filterLedger(entries, filter, method);

  const columns: DataTableColumn<CashLedgerEntry>[] = [
    { id: 'time', header: 'Hora', render: (entry) => formatTime(entry.at) },
    {
      id: 'kind',
      header: 'Tipo',
      render: (entry) => (
        <span className={`kind-badge kind-badge--${entry.kind}`}>
          <i aria-hidden="true" />
          {KIND_LABEL[entry.kind]}
        </span>
      ),
    },
    {
      id: 'document',
      header: 'Documento',
      render: (entry) => <span className="cashbox-mono">{entry.document ?? '—'}</span>,
    },
    { id: 'method', header: 'Medio de pago', render: (entry) => entry.paymentMethod ?? '—' },
    { id: 'description', header: 'Descripción', render: (entry) => entry.description },
    {
      id: 'amount',
      header: 'Importe',
      align: 'right',
      render: (entry) => {
        const value = Number(entry.amount);
        return (
          <span className={value < 0 ? 'cashbox-amount is-negative' : 'cashbox-amount'}>
            {value < 0 ? formatSignedMoney(value) : NumberUtils.formatCurrency(value)}
          </span>
        );
      },
    },
  ];

  return (
    <div className="shift-ledger" data-testid="shift-ledger">
      <div className="shift-ledger__filters">
        <div className="shift-ledger__chips" role="group" aria-label="Filtrar por tipo">
          {FILTERS.filter((item) => !filters || filters.includes(item.value)).map((item) => (
            <button
              key={item.value}
              type="button"
              className={`shift-chip${filter === item.value ? ' is-active' : ''}`}
              aria-pressed={filter === item.value}
              onClick={() => setFilter(item.value)}
            >
              {item.label} · {counts[item.value]}
            </button>
          ))}
        </div>
        <div className="shift-ledger__method">
          <Combobox
            aria-label="Medio de pago"
            placeholder="Medio de pago: todos"
            clearable
            options={methods.map((name) => ({ value: name, label: name }))}
            value={method}
            onChange={setMethod}
          />
        </div>
      </div>
      <DataTable
        columns={columns}
        rows={visible}
        getRowKey={(entry) => entry.id}
        variant="flush"
        emptyMessage={emptyMessage}
      />
      <footer className="shift-ledger__footer">
        Mostrando {visible.length} de {entries.length} movimientos
      </footer>
    </div>
  );
}
