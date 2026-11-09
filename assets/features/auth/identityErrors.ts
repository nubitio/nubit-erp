/** Spanish copy for the English messages of the identity module's public endpoints. */
const MESSAGES: Record<string, string> = {
  'This invitation is no longer valid. Ask for a new one.':
    'Esta invitación ya no es válida o venció. Pide una nueva a tu administrador.',
  'That address already has an account. Sign in or reset your password.':
    'Ese correo ya tiene una cuenta. Ingresa o restablece tu contraseña.',
  'The invitation does not carry a valid role. Ask for a new one.':
    'La invitación no tiene un rol válido. Pide una nueva a tu administrador.',
};

export const MIN_PASSWORD_LENGTH = 8;

export function describeIdentityError(err: unknown, fallback: string): string {
  const message = (err as { data?: { message?: string } })?.data?.message;
  if (!message) return fallback;
  if (MESSAGES[message]) return MESSAGES[message];
  if (/at least \d+ characters/.test(message)) {
    return `La contraseña debe tener al menos ${MIN_PASSWORD_LENGTH} caracteres.`;
  }
  if (/token|invalid|expired|no longer valid/i.test(message)) {
    return 'El enlace no es válido o ya venció. Solicita uno nuevo.';
  }
  return fallback;
}

/** Client-side check shared by the invitation and reset forms. Returns an error or null. */
export function validateNewPassword(password: string, confirmation: string): string | null {
  if (password.length < MIN_PASSWORD_LENGTH) {
    return `La contraseña debe tener al menos ${MIN_PASSWORD_LENGTH} caracteres.`;
  }
  if (password !== confirmation) return 'Las contraseñas no coinciden.';
  return null;
}
