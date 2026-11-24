import { AppDialog, Button, Combobox, FormField, TextField } from '@nubitio/ui';
import { useState } from 'react';
import toast from '@shared/lib/SharedUtils';
import { treasuryErrorMessage, useCreateWallet } from './api/treasuryApi';

interface Props {
  onClose: () => void;
}

const CURRENCIES = ['PEN', 'USD'];

export const NewWalletDialog = ({ onClose }: Props) => {
  const create = useCreateWallet();
  const [name, setName] = useState('');
  const [currency, setCurrency] = useState('PEN');

  const submit = () => {
    if (name.trim() === '') {
      toast('Escribe un nombre para la cuenta', 'error');
      return;
    }
    create.mutate(
      { name: name.trim(), currency },
      {
        onSuccess: () => {
          toast('Billetera creada', 'success');
          onClose();
        },
        onError: (error) =>
          toast(treasuryErrorMessage(error, 'No se pudo crear la billetera'), 'error', 6000),
      },
    );
  };

  return (
    <AppDialog
      open
      title="Nueva billetera"
      onClose={onClose}
      maxWidth={440}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            Cancelar
          </Button>
          <Button loading={create.isPending} disabled={create.isPending} onClick={submit}>
            Crear
          </Button>
        </>
      }
    >
      <FormField label="Nombre" helpText="Por ejemplo Yape, Plin o Tarjetas Izipay.">
        <TextField
          value={name}
          onChange={(e) => setName(e.target.value)}
          maxLength={120}
          autoFocus
        />
      </FormField>
      <FormField label="Moneda">
        <Combobox
          aria-label="Moneda"
          clearable={false}
          options={CURRENCIES.map((code) => ({ value: code, label: code }))}
          value={currency}
          onChange={(code) => code && setCurrency(code)}
        />
      </FormField>
    </AppDialog>
  );
};
