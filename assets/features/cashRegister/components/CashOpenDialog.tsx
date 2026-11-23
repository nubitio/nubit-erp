import { useState } from 'react';
import { Alert, AppDialog, Button, Combobox, FormField, Stack, TextField } from '@nubitio/ui';
import toast from '@shared/lib/SharedUtils';
import { serverErrorMessage } from '@shared/lib/formErrors';
import { hasPermission } from '@shared/permissions';
import {
  cashSessionService,
  type CashBusyRegister,
  type CashRegisterOption,
} from '../services/cashSessionService';

interface Props {
  open: boolean;
  /** Registers of the branch that have no open session. */
  registers: CashRegisterOption[];
  /** Registers someone else has open, to say whom to wait for when none is free. */
  busyRegisters?: CashBusyRegister[];
  onClose: () => void;
  /** Called once the server has opened the session; the caller refreshes its state. */
  onOpened: () => void | Promise<void>;
}

const FORM_ID = 'cash-open-form';
const openedTime = (value: string) =>
  new Date(value).toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' });
const OPEN_ERROR = 'No se pudo abrir la caja. Inténtalo de nuevo.';

/** Opens a cash session. The register is asked only when the branch has more than one free. */
export function CashOpenDialog({ open, registers, busyRegisters = [], onClose, onOpened }: Props) {
  const [registerId, setRegisterId] = useState<string>('');
  const [openingBalance, setOpeningBalance] = useState('0.00');
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const selectedId = registers.some((register) => String(register.id) === registerId)
    ? registerId
    : String(registers[0]?.id ?? '');
  const amount = Number(openingBalance);
  const amountInvalid = openingBalance.trim() === '' || !Number.isFinite(amount) || amount < 0;

  function close() {
    if (saving) return;
    setError(null);
    onClose();
  }

  async function submit(event: React.FormEvent) {
    event.preventDefault();
    if (saving || amountInvalid || selectedId === '') return;
    setSaving(true);
    setError(null);
    try {
      await cashSessionService.open(Number(selectedId), amount);
      toast('Caja abierta', 'success');
      setOpeningBalance('0.00');
      setRegisterId('');
      await onOpened();
    } catch (failure) {
      setError(serverErrorMessage(failure, OPEN_ERROR));
      // The register may have been taken meanwhile: let the caller reload the free ones.
      void onOpened();
    } finally {
      setSaving(false);
    }
  }

  return (
    <AppDialog
      open={open}
      title="Abrir caja"
      onClose={close}
      width={420}
      closeOnEscape={!saving}
      showCloseButton={!saving}
      footer={
        <>
          <Button variant="secondary" onClick={close} disabled={saving}>
            Cancelar
          </Button>
          <Button
            type="submit"
            form={FORM_ID}
            variant="primary"
            loading={saving}
            disabled={amountInvalid || selectedId === ''}
            data-testid="cash-open-submit-btn"
          >
            Abrir caja
          </Button>
        </>
      }
    >
      {registers.length === 0 ? (
        <Stack gap={4}>
          {error && <Alert tone="danger">{error}</Alert>}
          <Alert tone="warning">
            {busyRegisters.length > 0
              ? 'Todas las cajas de esta sucursal tienen un turno abierto:'
              : 'Esta sucursal no tiene cajas. Pide a un administrador que cree una.'}
            {busyRegisters.length > 0 && (
              <ul>
                {busyRegisters.map((register) => (
                  <li key={register.id}>
                    <strong>{register.name}</strong>: {register.cashier ?? 'otro usuario'}, desde
                    las {openedTime(register.openedAt)}
                  </li>
                ))}
              </ul>
            )}
          </Alert>
          {busyRegisters.length > 0 && (
            <p>
              {hasPermission('cash.close_any')
                ? 'Como supervisor puedes cerrar el turno desde Finanzas › Cajas y turnos.'
                : 'Pide a esa persona que cierre su turno, o a un supervisor que lo cierre.'}
            </p>
          )}
        </Stack>
      ) : (
        <form id={FORM_ID} onSubmit={(event) => void submit(event)} noValidate>
          <Stack gap={4}>
            {error && <Alert tone="danger">{error}</Alert>}
            {registers.length > 1 ? (
              <FormField label="Caja" required htmlFor="cash-open-register">
                <Combobox
                  id="cash-open-register"
                  aria-label="Caja"
                  required
                  options={registers.map((register) => ({
                    value: String(register.id),
                    label: register.name,
                  }))}
                  value={selectedId}
                  onChange={(value) => setRegisterId(value ?? '')}
                />
              </FormField>
            ) : (
              <p>
                Se abrirá <strong>{registers[0]?.name}</strong>.
              </p>
            )}
            <FormField
              label="Efectivo inicial (S/)"
              required
              htmlFor="cash-open-balance"
              error={amountInvalid ? 'Ingresa un monto de cero o más.' : undefined}
              helpText="El dinero con el que empieza el turno, contado en caja."
            >
              <TextField
                id="cash-open-balance"
                type="number"
                inputMode="decimal"
                min={0}
                step={0.01}
                required
                autoFocus
                invalid={amountInvalid}
                value={openingBalance}
                onChange={(event) => setOpeningBalance(event.target.value)}
              />
            </FormField>
          </Stack>
        </form>
      )}
    </AppDialog>
  );
}
