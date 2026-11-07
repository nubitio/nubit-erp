/**
 * The identity module answers a login that needs a second factor with 401 and an English
 * message key (nubit/admin-bundle TotpListener). The form branches on these exact strings, so
 * they live in one place with the Spanish copy.
 */
export const SECOND_FACTOR_REQUIRED = 'Second factor required.';
export const SECOND_FACTOR_ENROLMENT_REQUIRED = 'Second factor enrolment required.';

export type LoginErrorKind = 'needs-code' | 'enrolment-required' | 'bad-code' | 'other';

export interface ClassifiedLoginError {
  kind: LoginErrorKind;
  /** User-facing message; undefined means "use the generic/server message". */
  text?: string;
}

export function classifyLoginError(message: string | undefined): ClassifiedLoginError {
  switch (message) {
    case SECOND_FACTOR_REQUIRED:
      return { kind: 'needs-code' };
    case SECOND_FACTOR_ENROLMENT_REQUIRED:
      return {
        kind: 'enrolment-required',
        text: 'Tu cuenta debe activar la verificación en dos pasos. Pide ayuda a un administrador.',
      };
    case 'That code is not valid.':
      return {
        kind: 'bad-code',
        text: 'El código no es válido. Revisa tu app de autenticación o usa un código de recuperación.',
      };
    case 'That code is not valid. Check the clock on your device and try again.':
      return {
        kind: 'bad-code',
        text: 'El código no es válido. Revisa la hora de tu teléfono e inténtalo de nuevo.',
      };
    case 'This account already has a second factor; remove it before enrolling again.':
      return { kind: 'other', text: 'La verificación en dos pasos ya está activa en tu cuenta.' };
    case 'Start enrolment before confirming it.':
      return { kind: 'other', text: 'Primero inicia la activación para obtener tu clave.' };
    case 'This second factor is already confirmed.':
      return { kind: 'other', text: 'La verificación en dos pasos ya estaba confirmada.' };
    case 'This account has no confirmed second factor.':
      return { kind: 'other', text: 'Tu cuenta no tiene la verificación en dos pasos activa.' };
    case 'That code has already been used. Wait for the next one.':
      return {
        kind: 'bad-code',
        text: 'Ese código ya se usó. Espera al siguiente e inténtalo de nuevo.',
      };
    default:
      return { kind: 'other' };
  }
}
