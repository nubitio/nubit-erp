import { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Alert, AppDialog, Button, FormField, Stack, TextField } from '@nubitio/ui';
import toast from '@shared/lib/SharedUtils';
import { serverErrorMessage } from '@shared/lib/formErrors';
import { cashSessionService, type CashMovementKind } from '../services/cashSessionService';

const FORM_ID = 'register-movement-form';

const TEXT: Record<CashMovementKind, { title: string; submit: string; done: string }> = {
  income: { title: 'Registrar ingreso', submit: 'Registrar ingreso', done: 'Ingreso registrado' },
  expense: { title: 'Registrar egreso', submit: 'Registrar egreso', done: 'Egreso registrado' },
  withdrawal: {
    title: 'Retirar efectivo',
    submit: 'Registrar retiro',
    done: 'Retiro registrado',
  },
};

interface Props {
  open: boolean;
  sessionId: number;
  /** Money coming into the drawer, going out of it, or taken to the safe. */
  kind: CashMovementKind;
  onClose: () => void;
}

/** Registers a manual income, expense or withdrawal in an open shift: the person writes only the reason. */
export function RegisterMovementDialog({ open, sessionId, kind, onClose }: Props) {
  const queryClient = useQueryClient();
  const [amount, setAmount] = useState('');
  const [description, setDescription] = useState('');
  const [error, setError] = useState<string | null>(null);

  const register = useMutation({
    mutationFn: () =>
      cashSessionService.addMovement({
        sessionId,
        kind,
        amount: Number(amount),
        description: description.trim(),
      }),
    onSuccess: async () => {
      toast(TEXT[kind].done, 'success');
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['cash-session-ledger', sessionId] }),
        queryClient.invalidateQueries({ queryKey: ['cash-x-report', sessionId] }),
      ]);
      reset();
      onClose();
    },
    onError: (failure) =>
      setError(serverErrorMessage(failure, 'No se pudo registrar el movimiento.')),
  });

  function reset() {
    setAmount('');
    setDescription('');
    setError(null);
  }

  function close() {
    if (register.isPending) return;
    reset();
    onClose();
  }

  const value = Number(amount);
  const invalid = amount.trim() === '' || !Number.isFinite(value) || value <= 0;
  const canSubmit = !invalid && description.trim() !== '' && !register.isPending;

  return (
    <AppDialog
      open={open}
      onClose={close}
      title={TEXT[kind].title}
      closeOnEscape={!register.isPending}
      showCloseButton={!register.isPending}
      footer={
        <>
          <Button variant="secondary" onClick={close} disabled={register.isPending}>
            Cancelar
          </Button>
          <Button
            type="submit"
            form={FORM_ID}
            variant="primary"
            loading={register.isPending}
            disabled={!canSubmit}
            data-testid="cash-movement-submit-btn"
          >
            {TEXT[kind].submit}
          </Button>
        </>
      }
    >
      <form
        id={FORM_ID}
        noValidate
        onSubmit={(event) => {
          event.preventDefault();
          if (canSubmit) register.mutate();
        }}
      >
        <Stack gap={3}>
          {error && <Alert tone="danger">{error}</Alert>}
          <FormField label="Monto (S/)" required htmlFor="register-movement-amount">
            <TextField
              id="register-movement-amount"
              type="number"
              inputMode="decimal"
              min={0}
              step={0.01}
              required
              placeholder="0.00"
              value={amount}
              onChange={(event) => setAmount(event.target.value)}
            />
          </FormField>
          <FormField label="Motivo" required htmlFor="register-movement-description">
            <TextField
              id="register-movement-description"
              required
              maxLength={200}
              placeholder="Por qué entra o sale este dinero, por ejemplo: cambio del supervisor o depósito del día"
              value={description}
              onChange={(event) => setDescription(event.target.value)}
            />
          </FormField>
        </Stack>
      </form>
    </AppDialog>
  );
}
