import { AppDialog, Button, Combobox, FormField, TextField } from '@nubitio/ui';
import { useState } from 'react';
import toast from '@shared/lib/SharedUtils';
import { treasuryErrorMessage, useCreateTreasuryAccount } from './api/treasuryApi';

interface Props {
  onClose: () => void;
}

const CURRENCIES = ['PEN', 'USD'];

export const NewWalletDialog = ({ onClose }: Props) => {
  const create = useCreateTreasuryAccount();
  const [kind, setKind] = useState<'wallet' | 'clearing'>('wallet');
  const [name, setName] = useState('');
  const [currency, setCurrency] = useState('PEN');

  const submit = () => {
    if (name.trim() === '') {
      toast('Escribe un nombre para la cuenta', 'error');
      return;
    }
    create.mutate(
      { name: name.trim(), currency, kind },
      {
        onSuccess: () => {
          toast('Cuenta creada', 'success');
          onClose();
        },
        onError: (error) =>
          toast(treasuryErrorMessage(error, 'No se pudo crear la cuenta'), 'error', 6000),
      },
    );
  };

  return (
    <AppDialog
      open
      title="Nueva cuenta de dinero"
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
      <FormField label="Tipo de cuenta">
        <Combobox
          aria-label="Tipo de cuenta"
          clearable={false}
          options={[
            { value: 'wallet', label: 'Billetera' },
            { value: 'clearing', label: 'Fondos por liquidar' },
          ]}
          value={kind}
          onChange={(value) => {
            if (value === 'wallet' || value === 'clearing') setKind(value);
          }}
        />
      </FormField>
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
