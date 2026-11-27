import { Button, FormField, TextField } from '@nubitio/ui';
import { EntityDropdown } from '@nubitio/crud';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { MoneyAccountPicker } from '../treasury/MoneyAccountPicker';

const API = getCoreApiBaseUrl();

export interface TenderRow {
  key: number;
  methodId: string | null;
  accountId: string;
  /** Only used when there is more than one tender: with one, the amount received is its amount. */
  amount: string;
  reference: string;
}

interface Props {
  rows: TenderRow[];
  currency: string;
  disabled: boolean;
  onChange: (rows: TenderRow[]) => void;
}

const METHOD_FILTERS = [
  { field: 'settlementBehavior', operator: '=', value: 'COLLECTED' },
  { field: 'isActive', operator: '=', value: 1 },
];

/** How the money arrived. One row by default; "Agregar otro medio" splits it (cash plus transfer, say). */
export const CollectionTenderRows = ({ rows, currency, disabled, onChange }: Props) => {
  const several = rows.length > 1;
  const update = (key: number, patch: Partial<TenderRow>) =>
    onChange(rows.map((row) => (row.key === key ? { ...row, ...patch } : row)));

  return (
    <div className="mb-3">
      {rows.map((row, index) => (
        <div key={row.key} className="mb-3" data-testid={`receivable-tender-${index}`}>
          <FormField label="Método de pago" required htmlFor={`receivable-payment-method-${index}`}>
            <EntityDropdown
              url={API + 'payment-methods'}
              filters={METHOD_FILTERS}
              value={row.methodId}
              onChange={(value) => update(row.key, { methodId: value, accountId: '' })}
              placeholder="Selecciona un método de pago"
              searchExpr={['name']}
            />
          </FormField>
          <MoneyAccountPicker
            direction="in"
            currency={currency}
            paymentMethodId={row.methodId}
            value={row.accountId}
            onChange={(accountId) => update(row.key, { accountId })}
            disabled={disabled}
          />
          {several && (
            <FormField
              label="Importe de este medio"
              required
              htmlFor={`receivable-tender-amount-${index}`}
            >
              <TextField
                id={`receivable-tender-amount-${index}`}
                type="number"
                min={0.01}
                step={0.01}
                value={row.amount}
                disabled={disabled}
                onChange={(event) => update(row.key, { amount: event.target.value })}
              />
            </FormField>
          )}
          <FormField label="Referencia" htmlFor={`receivable-payment-reference-${index}`}>
            <TextField
              id={`receivable-payment-reference-${index}`}
              placeholder="N° de operación"
              value={row.reference}
              disabled={disabled}
              onChange={(event) => update(row.key, { reference: event.target.value })}
            />
          </FormField>
          {several && (
            <Button
              type="button"
              variant="ghost"
              size="sm"
              disabled={disabled}
              onClick={() => onChange(rows.filter((r) => r.key !== row.key))}
            >
              Quitar este medio
            </Button>
          )}
        </div>
      ))}
      <Button
        type="button"
        variant="secondary"
        size="sm"
        disabled={disabled}
        data-testid="receivable-payment-add-tender"
        onClick={() =>
          onChange([
            ...rows,
            {
              key: Math.max(...rows.map((r) => r.key)) + 1,
              methodId: null,
              accountId: '',
              amount: '',
              reference: '',
            },
          ])
        }
      >
        Agregar otro medio
      </Button>
    </div>
  );
};
