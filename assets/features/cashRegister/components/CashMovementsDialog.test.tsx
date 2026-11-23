import { cleanup, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { hasPermission } from '@shared/permissions';
import { cashSessionService } from '../services/cashSessionService';
import { CashMovementsDialog } from './CashMovementsDialog';

const getXReport = vi.fn();

vi.mock('@shared/lib/SharedUtils', () => ({ default: vi.fn() }));
vi.mock('@shared/permissions', () => ({ hasPermission: vi.fn() }));
vi.mock('../services/cashRegisterService', () => ({
  default: () => ({ getXReport }),
}));
vi.mock('../services/cashSessionService', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../services/cashSessionService')>()),
  cashSessionService: {
    getMovements: vi.fn(),
    getMovementCategories: vi.fn(),
    addMovement: vi.fn(),
  },
}));

const service = vi.mocked(cashSessionService);
const can = vi.mocked(hasPermission);

const report = {
  openingBalance: '200.00',
  totalSales: '225.00',
  cashIn: '5.00',
  cashOut: '12.50',
  currentBalance: '417.50',
  shiftDurationMinutes: 90,
  salesByPaymentMethod: [
    { paymentMethodId: 1, paymentMethod: 'Efectivo', amount: '201.50' },
    { paymentMethodId: 7, paymentMethod: 'Yape', amount: '23.50' },
  ],
};
const movements = [
  { id: 1, type: 'income' as const, amount: '200.00', description: 'Saldo inicial' },
  {
    id: 2,
    type: 'expense' as const,
    amount: '12.50',
    description: 'Bolsas',
    createdAt: '2026-11-23T14:54:00+00:00',
    category: { id: 6, name: 'Gasto de caja chica', type: 'expense' as const },
  },
];
const categories = [
  { id: 4, name: 'Otros ingresos', type: 'income' as const },
  { id: 6, name: 'Gasto de caja chica', type: 'expense' as const },
];

let client: QueryClient;
const renderDialog = () =>
  render(
    <QueryClientProvider client={client}>
      <CashMovementsDialog open sessionId={371} registerName="Caja 3" onClose={vi.fn()} />
    </QueryClientProvider>,
  );

beforeEach(() => {
  client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  getXReport.mockReset().mockResolvedValue({ data: report });
  service.getMovements.mockReset().mockResolvedValue(movements);
  service.getMovementCategories.mockReset().mockResolvedValue(categories);
  service.addMovement.mockReset().mockResolvedValue({ data: {} } as never);
  can.mockReset().mockReturnValue(true);
});
afterEach(() => {
  cleanup();
  client.clear();
});

describe('CashMovementsDialog', () => {
  it('shows the partial cut with the sales by payment method', async () => {
    renderDialog();

    expect(await screen.findByText('Saldo en caja')).toBeTruthy();
    expect(screen.getByText('S/ 417.50')).toBeTruthy();
    expect(screen.getByText('Reporte X · Caja 3')).toBeTruthy();
    const methods = within(screen.getByLabelText('Ventas por método'));
    expect(methods.getByText('Yape')).toBeTruthy();
    expect(methods.getByText('S/ 23.50')).toBeTruthy();
  });

  it('shows what was left on credit apart from the payment methods, and always asks for a fresh cut', async () => {
    getXReport.mockResolvedValue({
      data: { ...report, receivableTotal: '19.90', receivableOperations: 1 },
    });
    renderDialog();

    const methods = within(await screen.findByLabelText('Ventas por método'));
    expect(methods.getByText(/A crédito \(por cobrar\)/)).toBeTruthy();
    expect(methods.getByText('S/ 19.90')).toBeTruthy();

    // A cut cached from the last time the dialog was open must not be shown as current.
    cleanup();
    renderDialog();
    await screen.findByLabelText('Ventas por método');
    expect(getXReport).toHaveBeenCalledTimes(2);
  });

  it('lists the cash moved by hand and leaves out the opening and the sales, which the cut counts', async () => {
    renderDialog();

    const list = await screen.findByTestId('cash-movements-list');

    expect(within(list).getByText('Gasto de caja chica')).toBeTruthy();
    expect(within(list).getByText('Bolsas')).toBeTruthy();
    expect(within(list).getByText(/12\.50/)).toBeTruthy();
    expect(within(list).queryByText('Saldo inicial')).toBeNull();
  });

  it('registers a movement and refreshes the cut and the list', async () => {
    renderDialog();
    await screen.findByTestId('cash-movements-list');
    service.getMovements.mockClear();
    getXReport.mockClear();

    await userEvent.click(screen.getByRole('combobox', { name: 'Categoría' }));
    await userEvent.click(await screen.findByRole('option', { name: /Gasto de caja chica/ }));
    await userEvent.type(screen.getByLabelText(/Monto/), '8.40');
    await userEvent.type(screen.getByLabelText(/Descripción/), 'Cinta');
    await userEvent.click(screen.getByTestId('cash-movement-submit-btn'));

    await waitFor(() =>
      expect(service.addMovement).toHaveBeenCalledWith({
        sessionId: 371,
        categoryId: 6,
        amount: 8.4,
        description: 'Cinta',
      }),
    );
    await waitFor(() => expect(service.getMovements).toHaveBeenCalled());
    await waitFor(() => expect(getXReport).toHaveBeenCalled());
  });

  it('keeps the form and shows the server explanation when the movement is refused', async () => {
    service.addMovement.mockRejectedValue({
      status: 422,
      data: { detail: 'La sesión de caja no existe o está cerrada.' },
    });
    renderDialog();
    await screen.findByTestId('cash-movements-list');

    await userEvent.click(screen.getByRole('combobox', { name: 'Categoría' }));
    await userEvent.click(await screen.findByRole('option', { name: /Otros ingresos/ }));
    await userEvent.type(screen.getByLabelText(/Monto/), '5');
    await userEvent.type(screen.getByLabelText(/Descripción/), 'Aporte');
    await userEvent.click(screen.getByTestId('cash-movement-submit-btn'));

    expect(await screen.findByText('La sesión de caja no existe o está cerrada.')).toBeTruthy();
    expect((screen.getByLabelText(/Descripción/) as HTMLInputElement).value).toBe('Aporte');
  });

  it('does not submit without category, a positive amount and a description', async () => {
    renderDialog();
    await screen.findByTestId('cash-movements-list');
    const submit = screen.getByTestId('cash-movement-submit-btn') as HTMLButtonElement;
    expect(submit.disabled).toBe(true);

    await userEvent.click(screen.getByRole('combobox', { name: 'Categoría' }));
    await userEvent.click(await screen.findByRole('option', { name: /Otros ingresos/ }));
    await userEvent.type(screen.getByLabelText(/Monto/), '0');
    await userEvent.type(screen.getByLabelText(/Descripción/), 'Aporte');
    expect(submit.disabled).toBe(true);
  });

  it('is read-only for who cannot register movements', async () => {
    can.mockImplementation((permission: string) => permission !== 'cash.movement');
    renderDialog();

    expect(await screen.findByTestId('cash-movements-list')).toBeTruthy();
    expect(screen.queryByTestId('cash-movement-submit-btn')).toBeNull();
    expect(service.getMovementCategories).not.toHaveBeenCalled();
  });

  it('says there is nothing to show when no money was moved by hand', async () => {
    service.getMovements.mockResolvedValue([movements[0]]);
    renderDialog();

    expect(await screen.findByText('Sin movimientos manuales')).toBeTruthy();
  });
});
