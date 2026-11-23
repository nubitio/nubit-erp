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

  it('keeps "Cerrar caja" out of the bar and starts the closing from the menu', async () => {
    const onCloseCaja = vi.fn();
    renderHeader({ onCloseCaja });
    expect(screen.queryByRole('button', { name: 'Cerrar caja' })).toBeNull();

    await userEvent.click(screen.getByRole('button', { name: 'Más opciones' }));
    await userEvent.click(await screen.findByRole('menuitem', { name: /Cerrar caja/ }));
    expect(onCloseCaja).toHaveBeenCalledTimes(1);
  });

  it('asks for confirmation before closing the caja with items in the cart', async () => {
    const onCloseCaja = vi.fn();
    renderHeader({ onCloseCaja, cartItemsCount: 2, hasCartItems: true });

    await userEvent.click(screen.getByRole('button', { name: 'Más opciones' }));
    await userEvent.click(await screen.findByRole('menuitem', { name: /Cerrar caja/ }));
    expect(onCloseCaja).not.toHaveBeenCalled();
    expect(await screen.findByRole('alertdialog')).toBeTruthy();

    await userEvent.click(screen.getByRole('button', { name: 'Cerrar caja' }));
    expect(onCloseCaja).toHaveBeenCalledTimes(1);
  });

  it('opens the movements and the partial cut from the menu', async () => {
    const onCashMovements = vi.fn();
    renderHeader({ onCashMovements });

    await userEvent.click(screen.getByRole('button', { name: 'Más opciones' }));
    await userEvent.click(await screen.findByRole('menuitem', { name: /Movimientos y reporte X/ }));
    expect(onCashMovements).toHaveBeenCalledTimes(1);
  });

  it('offers no cash actions while the caja is closed', async () => {
    renderHeader({ cajaAbierta: false, onCloseCaja: vi.fn(), onCashMovements: vi.fn() });

    await userEvent.click(screen.getByRole('button', { name: 'Más opciones' }));
    expect(await screen.findByRole('menuitem', { name: /Atajos de teclado/ })).toBeTruthy();
    expect(screen.queryByRole('menuitem', { name: /Cerrar caja/ })).toBeNull();
    expect(screen.queryByRole('menuitem', { name: /Movimientos y reporte X/ })).toBeNull();
  });

  it('opens the caja from the bar while it is closed', async () => {
    const onOpenCaja = vi.fn();
    renderHeader({ cajaAbierta: false, onOpenCaja });
    await userEvent.click(screen.getByRole('button', { name: 'Abrir caja' }));
    expect(onOpenCaja).toHaveBeenCalledTimes(1);
  });

  it('does not claim open or closed until the server answers', () => {
    renderHeader({ cajaAbierta: false, cajaChecking: true });
    expect(screen.queryByRole('button', { name: 'Abrir caja' })).toBeNull();
    expect(screen.getByTestId('pos-caja-badge').textContent).toMatch(/Verificando/);
  });

  it('names the register the cashier has open', () => {
    renderHeader({ cajaName: 'Caja 2' });
    expect(screen.getByTestId('pos-caja-badge').textContent).toMatch(/Abierta · Caja 2/);
  });
});

describe('POSOperatorHeader session', () => {
  it('opens the user profile from the menu', async () => {
    const onOpenProfile = vi.fn();
    renderHeader({ onOpenProfile });
    await userEvent.click(screen.getByRole('button', { name: 'Más opciones' }));
    await userEvent.click(await screen.findByRole('menuitem', { name: /Mi perfil/ }));
    expect(onOpenProfile).toHaveBeenCalledTimes(1);
  });

  it('signs out at once when there is no shift open and no cart to leave behind', async () => {
    const onSignOut = vi.fn();
    renderHeader({ onSignOut, cajaAbierta: false });
    await userEvent.click(screen.getByRole('button', { name: 'Más opciones' }));
    await userEvent.click(await screen.findByRole('menuitem', { name: /Cerrar sesión/ }));
    expect(onSignOut).toHaveBeenCalledTimes(1);
    expect(screen.queryByRole('alertdialog')).toBeNull();
  });

  it('warns that signing out does not close the shift, and signs out only when confirmed', async () => {
    const onSignOut = vi.fn();
    renderHeader({ onSignOut, cajaAbierta: true });
    await userEvent.click(screen.getByRole('button', { name: 'Más opciones' }));
    await userEvent.click(await screen.findByRole('menuitem', { name: /Cerrar sesión/ }));

    const dialog = await screen.findByRole('alertdialog');
    expect(dialog.textContent).toMatch(/no cierra el turno/);
    expect(onSignOut).not.toHaveBeenCalled();

    await userEvent.click(screen.getByRole('button', { name: 'Cerrar sesión' }));
    expect(onSignOut).toHaveBeenCalledTimes(1);
  });

  it('warns that the next person will see the cart', async () => {
    renderHeader({ onSignOut: vi.fn(), cajaAbierta: false, cartItemsCount: 2, hasCartItems: true });
    await userEvent.click(screen.getByRole('button', { name: 'Más opciones' }));
    await userEvent.click(await screen.findByRole('menuitem', { name: /Cerrar sesión/ }));

    expect((await screen.findByRole('alertdialog')).textContent).toMatch(/quien entre después/);
  });

  it('lists profile and sign out as buttons in the options panel', () => {
    renderHeader({ variant: 'panel', onOpenProfile: vi.fn(), onSignOut: vi.fn() });
    expect(screen.getByRole('button', { name: 'Mi perfil' })).toBeTruthy();
    expect(screen.getByRole('button', { name: 'Cerrar sesión' })).toBeTruthy();
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
    renderHeader({
      variant: 'panel',
      onAssistantClick: vi.fn(),
      assistantEnabled: true,
      onCloseCaja: vi.fn(),
      onCashMovements: vi.fn(),
    });
    expect(screen.getByRole('button', { name: 'Cerrar caja' })).toBeTruthy();
    expect(screen.getByRole('button', { name: 'Movimientos y reporte X' })).toBeTruthy();
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
