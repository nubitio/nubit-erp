import { cleanup, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { cashSessionService } from '../services/cashSessionService';
import { CashOpenDialog } from './CashOpenDialog';

vi.mock('@shared/lib/SharedUtils', () => ({ default: vi.fn() }));
vi.mock('@shared/permissions', () => ({ hasPermission: vi.fn(() => false) }));
vi.mock('../services/cashSessionService', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../services/cashSessionService')>()),
  cashSessionService: { open: vi.fn() },
}));

const open = vi.mocked(cashSessionService.open);
const onClose = vi.fn();
const onOpened = vi.fn();

const render_ = (registers = [{ id: 3, name: 'Caja 3' }]) =>
  render(<CashOpenDialog open registers={registers} onClose={onClose} onOpened={onOpened} />);

beforeEach(() => {
  open.mockReset();
  onClose.mockReset();
  onOpened.mockReset();
});
afterEach(cleanup);

describe('CashOpenDialog', () => {
  it('opens the only free register without asking which', async () => {
    open.mockResolvedValue({ data: {} } as never);
    render_();

    expect(screen.queryByLabelText('Caja')).toBeNull();
    expect(screen.getByText('Caja 3')).toBeTruthy();

    await userEvent.clear(screen.getByLabelText(/Efectivo inicial/));
    await userEvent.type(screen.getByLabelText(/Efectivo inicial/), '150');
    await userEvent.click(screen.getByTestId('cash-open-submit-btn'));

    await waitFor(() => expect(open).toHaveBeenCalledWith(3, 150));
    expect(onOpened).toHaveBeenCalledTimes(1);
  });

  it('asks which register to open when the branch has several free', async () => {
    open.mockResolvedValue({ data: {} } as never);
    render_([
      { id: 1, name: 'Caja 1' },
      { id: 2, name: 'Caja 2' },
    ]);

    await userEvent.selectOptions(screen.getByLabelText('Caja'), 'Caja 2');
    await userEvent.click(screen.getByTestId('cash-open-submit-btn'));

    await waitFor(() => expect(open).toHaveBeenCalledWith(2, 0));
  });

  it('does not submit a negative or empty opening amount', async () => {
    render_();
    const amount = screen.getByLabelText(/Efectivo inicial/);

    await userEvent.clear(amount);
    expect((screen.getByTestId('cash-open-submit-btn') as HTMLButtonElement).disabled).toBe(true);

    await userEvent.type(amount, '-5');
    expect((screen.getByTestId('cash-open-submit-btn') as HTMLButtonElement).disabled).toBe(true);
    expect(screen.getByText('Ingresa un monto de cero o más.')).toBeTruthy();
    expect(open).not.toHaveBeenCalled();
  });

  it('shows the server explanation and reloads the free registers when the register was taken', async () => {
    open.mockRejectedValue({
      status: 409,
      data: { detail: 'Esta caja ya tiene un turno abierto.' },
    });
    render_();

    await userEvent.click(screen.getByTestId('cash-open-submit-btn'));

    expect(await screen.findByText('Esta caja ya tiene un turno abierto.')).toBeTruthy();
    expect(onOpened).toHaveBeenCalledTimes(1);
    expect(onClose).not.toHaveBeenCalled();
  });

  it('names who has each register open when none is free, and whom to ask', () => {
    render(
      <CashOpenDialog
        open
        registers={[]}
        busyRegisters={[
          {
            id: 1,
            name: 'Caja Principal',
            cashier: 'maria.cajera',
            openedAt: '2026-11-23T16:55:00+00:00',
          },
        ]}
        onClose={onClose}
        onOpened={onOpened}
      />,
    );

    expect(
      screen.getByText(/Todas las cajas de esta sucursal tienen un turno abierto/),
    ).toBeTruthy();
    expect(screen.getByText('Caja Principal')).toBeTruthy();
    expect(screen.getByText(/maria\.cajera/)).toBeTruthy();
    expect(screen.getByText(/Pide a esa persona que cierre su turno/)).toBeTruthy();
  });

  it('says the branch has no registers when there is none to wait for', () => {
    render_([]);

    expect(screen.getByText(/no tiene cajas/)).toBeTruthy();
    expect(screen.getByTestId('cash-open-submit-btn')).toBeTruthy();
    expect((screen.getByTestId('cash-open-submit-btn') as HTMLButtonElement).disabled).toBe(true);
  });
});
