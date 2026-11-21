export interface TotpStatus {
  enrolled: boolean;
  pending: boolean;
  recoveryCodesLeft: number;
}

export interface TotpRow {
  /** Short state shown beside the name of the setting. */
  badge: { label: string; tone: 'success' | 'secondary' };
  description: string;
  /** What the button does from here: switch it on, or look after it. */
  action: string;
}

const LOW_CODES = 2;

/**
 * How the "Verificación en dos pasos" row reads. An unfinished enrolment (`pending`) is not in force,
 * so it reads as off: the person has to confirm a code from their app for it to count.
 */
export function describeTotp(status: TotpStatus): TotpRow {
  if (!status.enrolled) {
    return {
      badge: { label: 'Desactivada', tone: 'secondary' },
      description: 'Un código de tu teléfono además de la contraseña.',
      action: 'Activar',
    };
  }

  const left = status.recoveryCodesLeft;
  const few = left === 1 ? 'Te queda 1 código' : `Te quedan ${left} códigos`;
  return {
    badge: { label: 'Activada', tone: 'success' },
    description:
      left <= LOW_CODES
        ? `${few} de recuperación: genera nuevos pronto.`
        : `Se pide un código de tu teléfono al ingresar. Códigos de recuperación: ${left}.`,
    action: 'Administrar',
  };
}
