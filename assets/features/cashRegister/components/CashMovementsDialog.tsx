import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  Alert,
  AppDialog,
  Badge,
  Button,
  DescriptionList,
  EmptyState,
  FormField,
  SelectField,
  Skeleton,
  Stack,
  TextField,
} from '@nubitio/ui';
import toast from '@shared/lib/SharedUtils';
import NumberUtils from '@shared/lib/NumberUtils';
import { serverErrorMessage } from '@shared/lib/formErrors';
import { hasPermission } from '@shared/permissions';
import cashRegisterService from '../services/cashRegisterService';
import { cashSessionService, type CashSessionMovement } from '../services/cashSessionService';
import './CashMovementsDialog.scss';

interface Props {
  open: boolean;
  sessionId: number;
  registerName?: string;
  onClose: () => void;
}

const FORM_ID = 'cash-movement-form';
const money = (value: string | number) => NumberUtils.formatCurrency(Number(value));
const time = (value?: string) =>
  value ? new Date(value).toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' }) : '';

/**
 * What the cashier needs of the drawer mid-shift: the partial cut (Reporte X), the cash that came
 * in or went out by hand, and a way to register one. Sales are in the cut, not in the list.
 */
export function CashMovementsDialog({ open, sessionId, registerName, onClose }: Props) {
  const queryClient = useQueryClient();
  const canRegister = hasPermission('cash.movement');
  const [categoryId, setCategoryId] = useState('');
  const [amount, setAmount] = useState('');
  const [description, setDescription] = useState('');
  const [formError, setFormError] = useState<string | null>(null);

  const report = useQuery({
    queryKey: ['cash-x-report', sessionId],
    queryFn: () =>
      cashRegisterService()
        .getXReport(sessionId)
        .then((response) => response.data),
    enabled: open,
  });
  const movements = useQuery({
    queryKey: ['cash-session-movements', sessionId],
    queryFn: () => cashSessionService.getMovements(sessionId),
    enabled: open,
  });
  const categories = useQuery({
    queryKey: ['cash-movement-categories'],
    queryFn: () => cashSessionService.getMovementCategories(),
    enabled: open && canRegister,
    staleTime: 5 * 60_000,
  });

  const register = useMutation({
    mutationFn: () =>
      cashSessionService.addMovement({
        sessionId,
        categoryId: Number(categoryId),
        amount: Number(amount),
        description: description.trim(),
      }),
    onSuccess: async () => {
      toast('Movimiento registrado', 'success');
      setCategoryId('');
      setAmount('');
      setDescription('');
      setFormError(null);
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['cash-session-movements', sessionId] }),
        queryClient.invalidateQueries({ queryKey: ['cash-x-report', sessionId] }),
      ]);
    },
    onError: (failure) =>
      setFormError(serverErrorMessage(failure, 'No se pudo registrar el movimiento.')),
  });

  const amountNumber = Number(amount);
  const amountInvalid = amount.trim() === '' || !Number.isFinite(amountNumber) || amountNumber <= 0;
  const canSubmit =
    categoryId !== '' && !amountInvalid && description.trim() !== '' && !register.isPending;
  const income = (categories.data ?? []).filter((category) => category.type === 'income');
  const expense = (categories.data ?? []).filter((category) => category.type === 'expense');
  // Sales and the opening carry no category: the cut already counts them.
  const manual: CashSessionMovement[] = [...(movements.data ?? [])]
    .filter((movement) => movement.category != null)
    .sort((a, b) => String(b.createdAt ?? '').localeCompare(String(a.createdAt ?? '')));

  return (
    <AppDialog
      open={open}
      title="Movimientos de caja"
      onClose={onClose}
      width={880}
      maxWidth="96vw"
      footer={
        <Button variant="secondary" onClick={onClose}>
          Cerrar
        </Button>
      }
    >
      <div className="cash-movements-dialog">
        <section aria-labelledby="cash-x-title" className="cash-movements-dialog__cut">
          <h3 id="cash-x-title">
            Reporte X{registerName ? ` · ${registerName}` : ''}
            <small>Corte parcial, no cierra el turno</small>
          </h3>
          {report.isError ? (
            <Alert tone="warning">No se pudo cargar el reporte X.</Alert>
          ) : report.data ? (
            <>
              <DescriptionList
                items={[
                  { term: 'Efectivo inicial', value: money(report.data.openingBalance) },
                  { term: 'Ventas del turno', value: money(report.data.totalSales) },
                  { term: 'Ingresos de caja', value: money(report.data.cashIn) },
                  { term: 'Egresos de caja', value: money(report.data.cashOut) },
                  {
                    term: 'Saldo en caja',
                    value: <strong>{money(report.data.currentBalance)}</strong>,
                  },
                ]}
              />
              {report.data.salesByPaymentMethod.length > 0 && (
                <ul className="cash-movements-dialog__methods" aria-label="Ventas por método">
                  {report.data.salesByPaymentMethod.map((row) => (
                    <li key={row.paymentMethodId}>
                      <span>
                        {row.paymentMethod}
                        {row.operations != null && ` · ${row.operations}`}
                      </span>
                      <span>{money(row.amount)}</span>
                    </li>
                  ))}
                </ul>
              )}
            </>
          ) : (
            <Skeleton variant="text" lines={5} />
          )}
        </section>

        <section aria-labelledby="cash-movements-title" className="cash-movements-dialog__list">
          <h3 id="cash-movements-title">Ingresos y egresos del turno</h3>

          {canRegister && (
            <form
              id={FORM_ID}
              className="cash-movements-dialog__form"
              noValidate
              onSubmit={(event) => {
                event.preventDefault();
                if (canSubmit) register.mutate();
              }}
            >
              <Stack gap={3}>
                {formError && <Alert tone="danger">{formError}</Alert>}
                {categories.isError && (
                  <Alert tone="warning">No se pudieron cargar las categorías.</Alert>
                )}
                <FormField label="Categoría" required htmlFor="cash-movement-category">
                  <SelectField
                    id="cash-movement-category"
                    required
                    value={categoryId}
                    onChange={(event) => setCategoryId(event.target.value)}
                  >
                    <option value="">Selecciona una categoría</option>
                    {income.length > 0 && (
                      <optgroup label="Ingresos">
                        {income.map((category) => (
                          <option key={category.id} value={category.id}>
                            {category.name}
                          </option>
                        ))}
                      </optgroup>
                    )}
                    {expense.length > 0 && (
                      <optgroup label="Egresos">
                        {expense.map((category) => (
                          <option key={category.id} value={category.id}>
                            {category.name}
                          </option>
                        ))}
                      </optgroup>
                    )}
                  </SelectField>
                </FormField>
                <div className="cash-movements-dialog__pair">
                  <FormField label="Monto (S/)" required htmlFor="cash-movement-amount">
                    <TextField
                      id="cash-movement-amount"
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
                  <FormField label="Descripción" required htmlFor="cash-movement-description">
                    <TextField
                      id="cash-movement-description"
                      required
                      maxLength={200}
                      placeholder="Por qué entra o sale este dinero"
                      value={description}
                      onChange={(event) => setDescription(event.target.value)}
                    />
                  </FormField>
                </div>
                <div>
                  <Button
                    type="submit"
                    form={FORM_ID}
                    variant="primary"
                    loading={register.isPending}
                    disabled={!canSubmit}
                    data-testid="cash-movement-submit-btn"
                  >
                    Registrar movimiento
                  </Button>
                </div>
              </Stack>
            </form>
          )}

          {movements.isError ? (
            <Alert tone="warning">No se pudieron cargar los movimientos.</Alert>
          ) : movements.isLoading ? (
            <Skeleton variant="text" lines={3} />
          ) : manual.length === 0 ? (
            <EmptyState
              size="sm"
              icon="arrows-left-right"
              title="Sin movimientos manuales"
              description="Los ingresos y egresos que registres en este turno aparecen aquí."
            />
          ) : (
            <ul className="cash-movements-dialog__rows" data-testid="cash-movements-list">
              {manual.map((movement) => (
                <li key={movement.id}>
                  <Badge variant={movement.type === 'income' ? 'success' : 'danger'} outlined>
                    {movement.type === 'income' ? 'Ingreso' : 'Egreso'}
                  </Badge>
                  <span className="cash-movements-dialog__what">
                    <strong>{movement.category?.name}</strong>
                    <span>{movement.description}</span>
                  </span>
                  <span className="cash-movements-dialog__when">{time(movement.createdAt)}</span>
                  <strong className="cash-movements-dialog__amount">
                    {movement.type === 'income' ? '+' : '−'}
                    {money(movement.amount)}
                  </strong>
                </li>
              ))}
            </ul>
          )}
        </section>
      </div>
    </AppDialog>
  );
}
