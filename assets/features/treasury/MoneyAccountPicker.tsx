import { Combobox, FormField, Text } from '@nubitio/ui';
import { useTenantRuntimeConfig } from '@shared/hooks/useTenantRuntimeConfig';
import { CASH_METHOD_ID } from '../cashRegister/components/cashCountModel';
import { useTreasuryPosition } from './api/treasuryApi';
import { mappableAccounts } from './model/treasuryModel';

interface Props {
  /** Currency of the payment: only accounts in that currency are offered (no automatic conversion). */
  currency: string;
  paymentMethodId: string | null;
  /** The chosen account id, or '' for "automatic" (the method's mapping decides). */
  value: string;
  onChange: (accountId: string) => void;
  disabled?: boolean;
  /** `out`: money leaves (a payment to a supplier). `in`: money arrives (a collection or advance). */
  direction?: 'in' | 'out';
}

/**
 * Where a payment's money leaves from. Shown only when the tenant uses Treasury. Cash always leaves the user's
 * open drawer, so it gets a note instead of a choice; any other method defaults to its mapped account and lets
 * the user pick another bank or wallet in the same currency.
 */
export const MoneyAccountPicker = ({
  currency,
  paymentMethodId,
  value,
  onChange,
  disabled,
  direction = 'out',
}: Props) => {
  const config = useTenantRuntimeConfig();
  const enabled = config.data?.capabilities['treasury.accounts']?.enabled === true;
  const position = useTreasuryPosition(enabled);

  if (!enabled || paymentMethodId === null) return null;

  if (Number(paymentMethodId) === CASH_METHOD_ID) {
    return (
      <Text tone="muted" as="p">
        {direction === 'in'
          ? 'El efectivo entra en tu caja abierta y queda en el arqueo.'
          : 'El efectivo sale de tu caja abierta y queda en el arqueo.'}
      </Text>
    );
  }

  const options = mappableAccounts(position.data?.accounts ?? [], currency);

  return (
    <FormField
      label={direction === 'in' ? 'Cuenta de destino' : 'Cuenta de salida'}
      htmlFor="money-account-picker"
      helpText="Por defecto se usa la cuenta asignada a este método de pago."
    >
      <Combobox
        id="money-account-picker"
        placeholder="Automática (según el método)"
        clearable
        options={options.map((account) => ({ value: String(account.id), label: account.name }))}
        value={value === '' ? null : value}
        disabled={disabled || position.isPending}
        onChange={(accountId) => onChange(accountId ?? '')}
      />
    </FormField>
  );
};
