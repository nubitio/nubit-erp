import type { CashMovementKind } from '../services/cashSessionService';

/** The three things a person registers by hand; the system files each under its own reason. */
export const MOVEMENT_KIND_OPTIONS: { value: CashMovementKind; label: string }[] = [
  { value: 'income', label: 'Ingreso' },
  { value: 'expense', label: 'Egreso' },
  { value: 'withdrawal', label: 'Retiro de efectivo' },
];
