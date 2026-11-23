import { cleanup, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { cashSessionService, type CashShiftSales } from '../services/cashSessionService';
import { CashShiftSalesDialog } from './CashShiftSalesDialog';

vi.mock('../services/cashSessionService', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../services/cashSessionService')>()),
  cashSessionService: { getShiftSales: vi.fn() },
}));

const getShiftSales = vi.mocked(cashSessionService.getShiftSales);

const shift: CashShiftSales = {
  sessionId: 371,
  count: 3,
  total: '75.40',
  byMethod: [
    { id: 1, name: 'Efectivo', amount: '35.50', operations: 2 },
    { id: 2, name: 'Yape', amount: '20.00', operations: 1 },
  ],
  receivable: { amount: '19.90', operations: 1 },
  sales: [
    {
      id: 's-4',
      issuedAt: '2026-11-23T14:30:00+00:00',
      status: 'COMPLETED',
      total: '19.90',
      receivable: '19.90',
      documentType: '03',
      documentNumber: 'B001-13',
      sunatStatus: null,
      payments: [],
    },
    {
      id: 's-3',
      issuedAt: '2026-11-23T14:15:00+00:00',
      status: 'COMPLETED',
      total: '30.00',
      receivable: '0.00',
      documentType: '03',
      documentNumber: 'B001-12',
      sunatStatus: '0',
      payments: [
        { method: 'Efectivo', amount: '10.00' },
        { method: 'Yape', amount: '20.00' },
      ],
    },
    {
      id: 's-2',
      issuedAt: '2026-11-23T14:05:00+00:00',
      status: 'VOIDED',
      total: '999.00',
      receivable: '0.00',
      documentNumber: 'B001-11',
      sunatStatus: '0',
      payments: [{ method: 'Efectivo', amount: '999.00' }],
    },
    {
      id: 's-1',
      issuedAt: '2026-11-23T13:50:00+00:00',
      status: 'COMPLETED',
      total: '25.50',
      receivable: '0.00',
      documentNumber: 'B001-10',
      sunatStatus: '2000',
      payments: [{ method: 'Efectivo', amount: '25.50' }],
    },
    {
      id: 's-0',
      issuedAt: '2026-11-23T13:40:00+00:00',
      status: 'COMPLETED',
      total: '1.00',
      receivable: '0.00',
      documentNumber: null,
      sunatStatus: null,
      payments: [],
    },
  ],
};

let client: QueryClient;
const renderDialog = (onReprint?: (saleId: string) => void) =>
  render(
    <QueryClientProvider client={client}>
      <CashShiftSalesDialog open sessionId={371} onClose={vi.fn()} onReprint={onReprint} />
    </QueryClientProvider>,
  );

beforeEach(() => {
  client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  getShiftSales.mockReset();
});
afterEach(cleanup);

describe('CashShiftSalesDialog', () => {
  it('lists the sales with their receipt, payment and state, and totals the ones that count', async () => {
    getShiftSales.mockResolvedValue(shift);
    renderDialog();

    const list = await screen.findByTestId('cash-shift-sales-list');
    expect(screen.getByRole('status').textContent).toContain('3 ventas');
    expect(screen.getByRole('status').textContent).toMatch(/75[.,]40/);

    const rows = within(list).getAllByRole('listitem');
    expect(rows).toHaveLength(5);
    expect(within(rows[0]).getByText('A crédito')).toBeTruthy();
    expect(within(rows[0]).queryByText('Pendiente de envío')).toBeTruthy();

    const methods = within(screen.getByRole('list', { name: 'Ventas por método de pago' }));
    expect(methods.getByText('Efectivo')).toBeTruthy();
    expect(methods.getByText('Yape')).toBeTruthy();
    expect(methods.getByText('A crédito · por cobrar')).toBeTruthy();
    expect(within(rows[1]).getByText('B001-12')).toBeTruthy();
    expect(within(rows[1]).getByText(/Efectivo.*10.*Yape.*20/)).toBeTruthy();
    expect(within(rows[1]).getByText('Aceptada por SUNAT')).toBeTruthy();
    expect(within(rows[2]).getByText('Anulada')).toBeTruthy();
    expect(within(rows[3]).getByText('Rechazada por SUNAT')).toBeTruthy();
    expect(within(rows[4]).getByText('Sin comprobante')).toBeTruthy();
    expect(within(rows[4]).queryByText(/SUNAT/)).toBeNull();
  });

  it('reprints a sale but not a voided one', async () => {
    getShiftSales.mockResolvedValue(shift);
    const onReprint = vi.fn();
    renderDialog(onReprint);

    const buttons = await screen.findAllByRole('button', { name: 'Reimprimir' });
    expect(buttons).toHaveLength(4);
    await userEvent.click(buttons[1]);
    expect(onReprint).toHaveBeenCalledWith('s-3');
  });

  it('offers no reprint where the device cannot print', async () => {
    getShiftSales.mockResolvedValue(shift);
    renderDialog();

    await screen.findByTestId('cash-shift-sales-list');
    expect(screen.queryByRole('button', { name: 'Reimprimir' })).toBeNull();
  });

  it('says so when the shift has no sales yet', async () => {
    getShiftSales.mockResolvedValue({
      sessionId: 371,
      count: 0,
      total: '0.00',
      sales: [],
      byMethod: [],
      receivable: { amount: '0.00', operations: 0 },
    });
    renderDialog();

    expect(await screen.findByText('Todavía no hay ventas en este turno.')).toBeTruthy();
    expect(screen.getByRole('status').textContent).toContain('0 ventas');
  });

  it('lets the cashier retry when the list cannot be loaded', async () => {
    getShiftSales.mockRejectedValueOnce(new Error('network')).mockResolvedValue(shift);
    renderDialog();

    await userEvent.click(await screen.findByRole('button', { name: 'Reintentar' }));
    expect(await screen.findByTestId('cash-shift-sales-list')).toBeTruthy();
  });
});
