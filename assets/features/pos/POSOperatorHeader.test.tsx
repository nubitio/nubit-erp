import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { ThemeProvider } from '@nubitio/ui';
import { POSOperatorHeader } from './POSOperatorHeader';

vi.mock('./HardwareAgentDialog', () => ({ HardwareAgentDialog: () => null }));

afterEach(cleanup);

const base = {
  branchName: 'Principal',
  userName: 'admin',
  warehouses: [{ id: '1', name: 'Almacén Principal' }],
  selectedWarehouseId: '1',
  setSelectedWarehouseId: vi.fn(),
  cajaAbierta: true,
  setCajaAbierta: vi.fn(),
  cajaOpenedAt: null,
  cartItemsCount: 0,
  hasCartItems: false,
  isOnline: true,
  isSyncing: false,
  pendingSalesCount: 0,
  outboxSales: [],
  onRetrySale: vi.fn(),
  showWarehouseSelector: false,
  onOpenHeldSales: vi.fn(),
  onShowShortcuts: vi.fn(),
};

const renderHeader = (props: Partial<Parameters<typeof POSOperatorHeader>[0]> = {}) =>
  render(
    <MemoryRouter>
      <ThemeProvider>
        <POSOperatorHeader {...base} {...props} />
      </ThemeProvider>
    </MemoryRouter>,
  );

describe('POSOperatorHeader bar', () => {
  it('shows pending sales only when something is waiting', () => {
    const { unmount } = renderHeader({ pendingSalesCount: 0 });
    expect(screen.queryByRole('button', { name: /Ventas pendientes/ })).toBeNull();
    unmount();

    renderHeader({ pendingSalesCount: 2 });
    expect(screen.getByRole('button', { name: /Ventas pendientes: 2/ })).toBeTruthy();
  });

  it('shows the held-sales count only when there are held sales', () => {
    const { unmount } = renderHeader({ heldSalesCount: 0 });
    expect(screen.getByTestId('pos-held-sales-btn').textContent).not.toMatch(/\(/);
    unmount();

    renderHeader({ heldSalesCount: 3 });
    expect(screen.getByTestId('pos-held-sales-btn').textContent).toMatch(/\(3\)/);
  });

  it('keeps "Cerrar caja" out of the bar and reachable from the menu', async () => {
    const setCajaAbierta = vi.fn();
    renderHeader({ setCajaAbierta });
    expect(screen.queryByRole('button', { name: 'Cerrar caja' })).toBeNull();

    await userEvent.click(screen.getByRole('button', { name: 'Más opciones' }));
    await userEvent.click(await screen.findByRole('menuitem', { name: /Cerrar caja/ }));
    expect(setCajaAbierta).toHaveBeenCalledWith(false);
  });

  it('asks for confirmation before closing the caja with items in the cart', async () => {
    const setCajaAbierta = vi.fn();
    renderHeader({ setCajaAbierta, cartItemsCount: 2, hasCartItems: true });

    await userEvent.click(screen.getByRole('button', { name: 'Más opciones' }));
    await userEvent.click(await screen.findByRole('menuitem', { name: /Cerrar caja/ }));
    expect(setCajaAbierta).not.toHaveBeenCalled();
    expect(await screen.findByRole('alertdialog')).toBeTruthy();
  });

  it('offers "Abrir caja" in the bar while the caja is closed', () => {
    renderHeader({ cajaAbierta: false });
    expect(screen.getByRole('button', { name: 'Abrir caja' })).toBeTruthy();
  });
});

describe('POSOperatorHeader shortcuts', () => {
  it('delegates "Atajos de teclado" to the page', async () => {
    const onShowShortcuts = vi.fn();
    renderHeader({ onShowShortcuts });
    await userEvent.click(screen.getByRole('button', { name: 'Más opciones' }));
    await userEvent.click(await screen.findByRole('menuitem', { name: /Atajos de teclado/ }));
    expect(onShowShortcuts).toHaveBeenCalledTimes(1);
  });
});

describe('POSOperatorHeader panel', () => {
  it('lists every action as a full-width button for the options dialog', () => {
    renderHeader({ variant: 'panel', onAssistantClick: vi.fn(), assistantEnabled: true });
    expect(screen.getByRole('button', { name: 'Cerrar caja' })).toBeTruthy();
    expect(screen.getByRole('button', { name: 'Atajos de teclado' })).toBeTruthy();
    expect(screen.getByRole('button', { name: 'Hardware local' })).toBeTruthy();
    expect(screen.getByRole('button', { name: 'Asistente de IA' })).toBeTruthy();
    expect(screen.getByText('Principal')).toBeTruthy();
  });

  it('shows pending sales only when something is waiting', () => {
    const { unmount } = renderHeader({ variant: 'panel', pendingSalesCount: 0 });
    expect(screen.queryByRole('button', { name: /Ventas pendientes/ })).toBeNull();
    unmount();

    renderHeader({ variant: 'panel', pendingSalesCount: 1 });
    expect(screen.getByRole('button', { name: /Ventas pendientes: 1/ })).toBeTruthy();
  });
});
