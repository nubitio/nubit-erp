import { AppDialog, Button, FormField } from '@nubitio/ui';
import { EntityDropdown } from '@nubitio/crud';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { useState } from 'react';
import toast from '@shared/lib/SharedUtils';
import { useTenantRuntimeConfig } from '@shared/hooks/useTenantRuntimeConfig';
import { MoneyAccountPicker } from '../treasury/MoneyAccountPicker';
import { treasuryErrorMessage } from '../treasury/api/treasuryApi';
import payrollRunService from './services/payrollRunService';

const API = getCoreApiBaseUrl();

interface Props {
  runId: string;
  /** Net amount of the run, already formatted for display. */
  netLabel: string;
  onClose: () => void;
  onDone: () => void;
}

/**
 * Registers the payment of a payroll run. With Treasury on, the payment method is required so the money is
 * recorded on a real account (cash leaves the open drawer; anything else leaves a bank or wallet account).
 */
export const PayrollPayDialog = ({ runId, netLabel, onClose, onDone }: Props) => {
  const config = useTenantRuntimeConfig();
  const treasuryOn = config.data?.capabilities['treasury.accounts']?.enabled === true;
  const [paymentMethodId, setPaymentMethodId] = useState<string | null>(null);
  const [moneyAccountId, setMoneyAccountId] = useState('');
  const [busy, setBusy] = useState(false);

  const submit = () => {
    if (busy) return;
    if (treasuryOn && paymentMethodId === null) {
      toast('Elige el método con el que se pagó la planilla', 'error');
      return;
    }
    setBusy(true);
    payrollRunService()
      .pay(runId, {
        paymentMethodId: paymentMethodId === null ? undefined : Number(paymentMethodId),
        moneyAccountId: moneyAccountId || undefined,
      })
      .then(() => {
        toast('Corrida marcada como pagada', 'success');
        onDone();
      })
      .catch((error) => {
        // The server says why (no open drawer, wrong currency…); show that instead of a generic message.
        toast(
          treasuryErrorMessage(error, 'No se pudo registrar el pago de la planilla'),
          'error',
          6000,
        );
      })
      .finally(() => setBusy(false));
  };

  return (
    <AppDialog
      open
      title="Registrar pago de planilla"
      onClose={onClose}
      maxWidth={480}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            Cancelar
          </Button>
          <Button loading={busy} disabled={busy} onClick={submit}>
            Marcar como pagada
          </Button>
        </>
      }
    >
      <p>
        Se pagará el neto de la corrida: <strong>{netLabel}</strong>.
      </p>
      <FormField
        label={treasuryOn ? 'Método de pago' : 'Método de pago (opcional)'}
        htmlFor="payroll-pay-method"
      >
        <EntityDropdown
          id="payroll-pay-method"
          url={API + 'payment-methods'}
          filters={[
            { field: 'settlementBehavior', operator: '=', value: 'COLLECTED' },
            { field: 'isActive', operator: '=', value: 1 },
          ]}
          value={paymentMethodId}
          onChange={(value) => {
            setPaymentMethodId(value);
            setMoneyAccountId('');
          }}
          placeholder="Selecciona un método de pago"
          searchExpr={['name']}
        />
      </FormField>
      <MoneyAccountPicker
        currency="PEN"
        paymentMethodId={paymentMethodId}
        value={moneyAccountId}
        onChange={setMoneyAccountId}
        disabled={busy}
      />
    </AppDialog>
  );
};
