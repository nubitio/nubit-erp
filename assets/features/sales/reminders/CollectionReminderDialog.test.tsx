import { cleanup, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { CollectionReminderDialog } from './CollectionReminderDialog';
import {
  collectionReminderService,
  type ReminderPreview,
  type ReminderRecord,
} from './collectionReminderService';

vi.mock('@shared/lib/SharedUtils', () => ({ default: vi.fn() }));
vi.mock('./collectionReminderService', async (importOriginal) => ({
  ...(await importOriginal<typeof import('./collectionReminderService')>()),
  collectionReminderService: { preview: vi.fn(), create: vi.fn() },
}));

const preview: ReminderPreview = {
  recipient: { name: 'Rosa Quispe', phone: '51954321678', email: null, source: 'customer' },
  subject: null,
  body: 'Hola Rosa Quispe, le escribimos de Compu Express.',
  documentUrl: null,
  daysOverdue: 43,
  whatsappUrl: 'https://wa.me/51954321678?text=Hola',
  lastReminder: null,
};

const record = (patch: Partial<ReminderRecord> = {}): ReminderRecord => ({
  id: 1,
  saleId: 'sale-1',
  channel: 'whatsapp',
  tone: 'direct',
  status: 'prepared',
  body: preview.body,
  failureReason: null,
  createdAt: '2026-11-03T00:00:00+00:00',
  whatsappUrl: 'https://wa.me/51954321678?text=Hola',
  ...patch,
});

function renderDialog(onClose = vi.fn()) {
  render(
    <QueryClientProvider
      client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
    >
      <CollectionReminderDialog
        saleId="sale-1"
        documentNumber="F001-00000262"
        balanceDue="845.00"
        currency="PEN"
        customerName="Bodega San José"
        onClose={onClose}
      />
    </QueryClientProvider>,
  );

  return onClose;
}

beforeEach(() => {
  vi.mocked(collectionReminderService.preview).mockResolvedValue(preview);
  vi.spyOn(window, 'open').mockReturnValue(null);
});
afterEach(() => {
  cleanup();
  vi.clearAllMocks();
  vi.restoreAllMocks();
});

describe('CollectionReminderDialog', () => {
  it('shows the recipient, the overdue badge and the message', async () => {
    renderDialog();

    expect((await screen.findByTestId('reminder-message')).textContent).toContain(
      'Hola Rosa Quispe',
    );
    expect(screen.getByTestId('reminder-recipient').textContent).toContain('+51 954 321 678');
    expect(screen.queryByText('43 días vencida')).not.toBeNull();
  });

  it('opens WhatsApp with the prepared link and closes after recording it', async () => {
    vi.mocked(collectionReminderService.create).mockResolvedValue(record());
    const onClose = renderDialog();
    await screen.findByTestId('reminder-message');

    await userEvent.setup().click(screen.getByTestId('reminder-submit'));

    await waitFor(() => expect(onClose).toHaveBeenCalled());
    expect(collectionReminderService.create).toHaveBeenCalledWith({
      saleId: 'sale-1',
      channel: 'whatsapp',
      tone: 'direct',
      force: false,
    });
    expect(window.open).toHaveBeenCalledWith(
      'https://wa.me/51954321678?text=Hola',
      '_blank',
      'noopener,noreferrer',
    );
  });

  it('offers to send anyway when the 24 h rule refuses a repeat', async () => {
    vi.mocked(collectionReminderService.create)
      .mockRejectedValueOnce({
        status: 409,
        data: { error: 'Ya se envió un recordatorio por este canal el 02/11/2026 03:00.' },
      })
      .mockResolvedValueOnce(record());
    const onClose = renderDialog();
    await screen.findByTestId('reminder-message');
    const user = userEvent.setup();

    await user.click(screen.getByTestId('reminder-submit'));
    await user.click(await screen.findByRole('button', { name: 'Enviar de todos modos' }));

    await waitFor(() => expect(onClose).toHaveBeenCalled());
    expect(collectionReminderService.create).toHaveBeenLastCalledWith(
      expect.objectContaining({ force: true }),
    );
  });

  it('keeps the dialog open and explains a failed e-mail', async () => {
    vi.mocked(collectionReminderService.create).mockResolvedValue(
      record({
        channel: 'email',
        status: 'failed',
        failureReason: 'No se pudo enviar el correo: timeout',
        whatsappUrl: null,
      }),
    );
    const onClose = renderDialog();
    await screen.findByTestId('reminder-message');
    const user = userEvent.setup();

    await user.click(screen.getByText('Correo'));
    await user.click(screen.getByTestId('reminder-submit'));

    expect(await screen.findByText('No se pudo enviar el correo: timeout')).toBeTruthy();
    expect(onClose).not.toHaveBeenCalled();
    expect(window.open).not.toHaveBeenCalled();
  });

  it('shows the API reason when the customer has no contact data', async () => {
    vi.mocked(collectionReminderService.preview).mockRejectedValue({
      status: 422,
      data: { error: 'El cliente no tiene un teléfono válido registrado.' },
    });
    renderDialog();

    expect(
      await screen.findByText('El cliente no tiene un teléfono válido registrado.'),
    ).toBeTruthy();
    expect((screen.getByTestId('reminder-submit') as HTMLButtonElement).disabled).toBe(true);
  });
});
