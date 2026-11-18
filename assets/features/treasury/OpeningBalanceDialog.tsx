import { AppDialog, Button, FormField, TextField } from '@nubitio/ui';
import { useState } from 'react';
import toast from '@shared/lib/SharedUtils';
import {
  treasuryErrorMessage,
  useSetOpeningBalance,
  type TreasuryAccount,
} from './api/treasuryApi';

interface Props {
  account: TreasuryAccount;
  onClose: () => void;
}

export const OpeningBalanceDialog = ({ account, onClose }: Props) => {
  const save = useSetOpeningBalance();
  const [amount, setAmount] = useState('');
  const [date, setDate] = useState(new Date().toISOString().slice(0, 10));

  const submit = () => {
    const value = Number(amount);
    if (!Number.isFinite(value) || value <= 0) {
      toast('Ingresa un saldo mayor a 0', 'error');
      return;
    }
    save.mutate(
      { accountId: account.id, amount: amount.trim(), date },
      {
        onSuccess: () => {
          toast('Saldo inicial registrado', 'success');
          onClose();
        },
        onError: (error) =>
          toast(
            treasuryErrorMessage(error, 'No se pudo registrar el saldo inicial'),
            'error',
            6000,
          ),
      },
    );
  };

  return (
    <AppDialog
      open
      title={`Saldo inicial de ${account.name}`}
      onClose={onClose}
      maxWidth={440}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            Cancelar
          </Button>
          <Button loading={save.isPending} disabled={save.isPending} onClick={submit}>
            Guardar
          </Button>
        </>
      }
    >
      <FormField
        label={`Saldo en ${account.currency}`}
        helpText="Lo que hay en la cuenta hoy. Se puede registrar una sola vez; los cambios posteriores se hacen con ajustes."
      >
        <TextField
          inputMode="decimal"
          value={amount}
          onChange={(e) => setAmount(e.target.value)}
          autoFocus
        />
      </FormField>
      <FormField label="Fecha">
        <TextField type="date" value={date} onChange={(e) => setDate(e.target.value)} />
      </FormField>
    </AppDialog>
  );
};
