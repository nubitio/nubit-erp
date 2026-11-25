import { AppDialog, Button, Combobox, FormField, TextField } from '@nubitio/ui';
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
  // Suspense is a holding account, not a place to move money to. A drawer can be one side of a transfer.
  const movable = useMemo(
    () => accounts.filter((a) => a.active && a.kind !== 'suspense' && a.kind !== 'clearing'),
    [accounts],
  );
  const [from, setFrom] = useState(fromId ?? movable[0]?.id ?? '');
  const origin = movable.find((a) => a.id === from);
  const destinations = movable.filter(
    (a) =>
      a.id !== from &&
      a.currency === origin?.currency &&
      // Cash moves between a drawer and a bank or wallet, not between two drawers.
      !(a.kind === 'cash_register' && origin?.kind === 'cash_register'),
  );
  const [to, setTo] = useState('');
  const drawerInvolved =
    origin?.kind === 'cash_register' || movable.find((a) => a.id === to)?.kind === 'cash_register';
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
        <Combobox
          aria-label="Desde"
          clearable={false}
          options={movable.map((a) => ({
            value: String(a.id),
            label: `${a.name} (${a.currency})`,
          }))}
          value={from === '' ? null : from}
          onChange={(accountId) => {
            setFrom(accountId ?? '');
            setTo('');
          }}
        />
      </FormField>
      <FormField
        label="Hacia"
        helpText={
          drawerInvolved
            ? 'El efectivo se registra en tu caja abierta: debe ser la caja de esta transferencia.'
            : 'Solo cuentas en la misma moneda: no se convierte automáticamente.'
        }
      >
        <Combobox
          aria-label="Hacia"
          placeholder="Elige una cuenta"
          options={destinations.map((a) => ({ value: String(a.id), label: a.name }))}
          value={to === '' ? null : to}
          onChange={(accountId) => setTo(accountId ?? '')}
        />
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
