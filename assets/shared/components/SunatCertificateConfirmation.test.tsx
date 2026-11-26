import { cleanup, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, expect, it, vi } from 'vitest';
import {
  SunatCertificateConfirmation,
  validateSunatCertificate,
} from './SunatCertificateConfirmation';

afterEach(cleanup);

it('validates required SOL fields and the selected certificate', () => {
  expect(validateSunatCertificate(' ', '', null)).toEqual({
    solUser: 'Ingresa el usuario SOL.',
    solPass: 'Ingresa la clave SOL.',
    certificate: 'Adjunta el certificado digital.',
  });
  expect(validateSunatCertificate('SOL', 'clave', new File([], 'cert.p12')).certificate).toBe(
    'El archivo está vacío.',
  );
  expect(
    validateSunatCertificate('SOL', 'clave', new File(['test'], 'cert.P12')).certificate,
  ).toBeUndefined();
});

it('shows the password on demand and keeps reauthentication errors in the dialog', async () => {
  const user = userEvent.setup();
  const onClose = vi.fn();
  const onError = vi.fn();
  const onConfirm = vi
    .fn<(password: string) => Promise<void>>()
    .mockRejectedValueOnce({ data: { code: 'certificate_reauthentication_required' } })
    .mockResolvedValueOnce(undefined);
  render(<SunatCertificateConfirmation {...{ onClose, onError, onConfirm }} />);

  const password = screen.getByLabelText(/Contraseña actual del ERP/, { selector: 'input' });
  await user.click(screen.getByRole('button', { name: 'Guardar certificado' }));
  expect(screen.getByText('Ingresa la contraseña actual de tu cuenta ERP.')).toBeTruthy();
  expect(onConfirm).not.toHaveBeenCalled();

  await user.type(password, 'incorrecta');
  await user.click(screen.getByRole('button', { name: 'Mostrar contraseña' }));
  expect(password.getAttribute('type')).toBe('text');
  await user.click(screen.getByRole('button', { name: 'Ocultar contraseña' }));
  expect(password.getAttribute('type')).toBe('password');

  await user.click(screen.getByRole('button', { name: 'Guardar certificado' }));
  expect(await screen.findByText('La contraseña actual del ERP es incorrecta.')).toBeTruthy();
  expect(onClose).not.toHaveBeenCalled();
  expect(onError).not.toHaveBeenCalled();

  await user.clear(password);
  await user.type(password, 'correcta');
  await user.click(screen.getByRole('button', { name: 'Guardar certificado' }));
  await waitFor(() => expect(onClose).toHaveBeenCalledOnce());
  expect(onConfirm).toHaveBeenLastCalledWith('correcta');
});
