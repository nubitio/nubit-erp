import { FormField, SelectField, Text } from '@nubitio/ui';
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
}: Props) => {
  const config = useTenantRuntimeConfig();
  const enabled = config.data?.capabilities['treasury.accounts']?.enabled === true;
  const position = useTreasuryPosition(enabled);

  if (!enabled || paymentMethodId === null) return null;

  if (Number(paymentMethodId) === CASH_METHOD_ID) {
    return (
      <Text tone="muted" as="p">
        El efectivo sale de tu caja abierta y queda en el arqueo.
      </Text>
    );
  }

  const options = mappableAccounts(position.data?.accounts ?? [], currency);

  return (
    <FormField
      label="Cuenta de salida"
      htmlFor="money-account-picker"
      helpText="Por defecto sale de la cuenta asignada a este método de pago."
    >
      <SelectField
        id="money-account-picker"
        value={value}
        disabled={disabled || position.isPending}
        onChange={(event) => onChange(event.target.value)}
      >
        <option value="">Automática (según el método)</option>
        {options.map((account) => (
          <option key={account.id} value={account.id}>
            {account.name}
          </option>
        ))}
      </SelectField>
    </FormField>
  );
};
