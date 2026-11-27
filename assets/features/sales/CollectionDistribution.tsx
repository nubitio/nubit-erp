import { Alert, Button, Text, TextField } from '@nubitio/ui';
import NumberUtils from '@shared/lib/NumberUtils';
import { formatDate, formatInvoiceNumber } from '@shared/lib/displayFormat';
import type { ReceivableInvoice } from './services/receivableService';
import { fromCents, toCents, type Plan } from './lib/collectionPlan';

interface Props {
  invoices: ReceivableInvoice[];
  currency: string;
  plan: Plan;
  /** The user's own cents per invoice while editing; null = automatic. */
  manual: Record<string, string> | null;
  disabled: boolean;
  error: string | null;
  onEdit: () => void;
  onReset: () => void;
  onManualChange: (invoiceId: string, value: string) => void;
}

/** Where the money will go. Automatic (oldest due date first) until the user chooses to edit it. */
export const CollectionDistribution = ({
  invoices,
  currency,
  plan,
  manual,
  disabled,
  error,
  onEdit,
  onReset,
  onManualChange,
}: Props) => {
  const planned = new Map(plan.lines.map((line) => [line.invoiceId, line.cents]));
  const money = (cents: number) => NumberUtils.formatCurrency(cents / 100, currency);

  return (
    <section
      className="mb-3"
      aria-labelledby="receivable-distribution-title"
      data-testid="receivable-distribution"
    >
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <strong id="receivable-distribution-title">Distribución</strong>
        {invoices.length > 0 &&
          (manual === null ? (
            <Button type="button" variant="ghost" size="sm" disabled={disabled} onClick={onEdit}>
              Editar distribución
            </Button>
          ) : (
            <Button type="button" variant="ghost" size="sm" disabled={disabled} onClick={onReset}>
              Volver a automática
            </Button>
          ))}
      </div>

      {invoices.length === 0 ? (
        <Text tone="muted" as="p">
          No hay comprobantes pendientes en esta moneda: el importe quedará como saldo a favor.
        </Text>
      ) : (
        <ul style={{ margin: '6px 0', paddingLeft: 0, listStyle: 'none' }}>
          {invoices.map((invoice) => {
            const label = formatInvoiceNumber(invoice.invoiceFullNumber);
            const cents = planned.get(invoice.id) ?? 0;
            return (
              <li
                key={invoice.id}
                style={{
                  display: 'flex',
                  justifyContent: 'space-between',
                  gap: 12,
                  padding: '4px 0',
                }}
              >
                <span>
                  {label}
                  <small style={{ marginLeft: 8, color: 'var(--text-muted)' }}>
                    vence {formatDate(invoice.dueDate)} · debe {money(toCents(invoice.balanceDue))}
                  </small>
                </span>
                {manual === null ? (
                  <strong>{cents > 0 ? money(cents) : '—'}</strong>
                ) : (
                  <TextField
                    type="number"
                    min={0}
                    step={0.01}
                    aria-label={`Importe para ${label}`}
                    value={manual[invoice.id] ?? ''}
                    disabled={disabled}
                    onChange={(event) => onManualChange(invoice.id, event.target.value)}
                  />
                )}
              </li>
            );
          })}
        </ul>
      )}

      {error && <Alert tone="danger">{error}</Alert>}
      {plan.unappliedCents > 0 && !error && (
        <Alert tone="info">
          Quedarán {money(plan.unappliedCents)} como saldo a favor del cliente.
        </Alert>
      )}
    </section>
  );
};

export const centsOf = (value: string): number => toCents(value === '' ? '0' : value);
export { fromCents };
