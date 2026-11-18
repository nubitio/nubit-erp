import { AppDialog, Button, FormField, SelectField, TextField } from '@nubitio/ui';
import { useMemo, useState } from 'react';
import toast from '@shared/lib/SharedUtils';
import { treasuryErrorMessage, useTransfer, type TreasuryAccount } from './api/treasuryApi';

interface Props {
  accounts: TreasuryAccount[];
  /** Pre-selected origin (from a row's menu). */
  fromId?: string;
  onClose: () => void;
}

export const TransferDialog = ({ accounts, fromId, onClose }: Props) => {
  const transfer = useTransfer();
  // Drawers move through their own session (WP2); suspense is a holding account, not a place to move money to.
  const movable = useMemo(
    () => accounts.filter((a) => a.active && (a.kind === 'bank' || a.kind === 'wallet')),
    [accounts],
  );
  const [from, setFrom] = useState(fromId ?? movable[0]?.id ?? '');
  const origin = movable.find((a) => a.id === from);
  const destinations = movable.filter((a) => a.id !== from && a.currency === origin?.currency);
  const [to, setTo] = useState('');
  const [amount, setAmount] = useState('');
  const [reference, setReference] = useState('');

  const submit = () => {
    const value = Number(amount);
    if (!from || !to) {
      toast('Elige la cuenta de origen y la de destino', 'error');
      return;
    }
    if (!Number.isFinite(value) || value <= 0) {
      toast('Ingresa un monto mayor a 0', 'error');
      return;
    }
    transfer.mutate(
      {
        fromAccountId: from,
        toAccountId: to,
        amount: amount.trim(),
        reference: reference.trim() || undefined,
      },
      {
        onSuccess: () => {
          toast('Transferencia registrada', 'success');
          onClose();
        },
        onError: (error) =>
          toast(
            treasuryErrorMessage(error, 'No se pudo registrar la transferencia'),
            'error',
            6000,
          ),
      },
    );
  };

  return (
    <AppDialog
      open
      title="Transferir entre cuentas"
      onClose={onClose}
      maxWidth={460}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            Cancelar
          </Button>
          <Button loading={transfer.isPending} disabled={transfer.isPending} onClick={submit}>
            Transferir
          </Button>
        </>
      }
    >
      <FormField label="Desde">
        <SelectField
          value={from}
          onChange={(e) => {
            setFrom(e.target.value);
            setTo('');
          }}
        >
          {movable.map((a) => (
            <option key={a.id} value={a.id}>
              {a.name} ({a.currency})
            </option>
          ))}
        </SelectField>
      </FormField>
      <FormField
        label="Hacia"
        helpText="Solo cuentas en la misma moneda: no se convierte automáticamente."
      >
        <SelectField value={to} onChange={(e) => setTo(e.target.value)}>
          <option value="">Elige una cuenta</option>
          {destinations.map((a) => (
            <option key={a.id} value={a.id}>
              {a.name}
            </option>
          ))}
        </SelectField>
      </FormField>
      <FormField label={`Monto${origin ? ` en ${origin.currency}` : ''}`}>
        <TextField inputMode="decimal" value={amount} onChange={(e) => setAmount(e.target.value)} />
      </FormField>
      <FormField label="Referencia (opcional)">
        <TextField
          value={reference}
          onChange={(e) => setReference(e.target.value)}
          maxLength={120}
        />
      </FormField>
    </AppDialog>
  );
};
