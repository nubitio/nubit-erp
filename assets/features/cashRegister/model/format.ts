import NumberUtils from '@shared/lib/NumberUtils';

/** A shift's code as the cashier reads it: `T-0481`. */
export const shiftCode = (id: number | string) => `T-${String(id).padStart(4, '0')}`;

const pad = (value: number) => String(value).padStart(2, '0');

/** `23/11/2026`. */
export function formatDate(value: string | Date): string {
  const date = new Date(value);
  return `${pad(date.getDate())}/${pad(date.getMonth() + 1)}/${date.getFullYear()}`;
}

/** `08:02`. */
export function formatTime(value: string | Date): string {
  const date = new Date(value);
  return `${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

/** `23/11/2026 08:02`. */
export function formatDateTime(value: string | Date): string {
  return `${formatDate(value)} ${formatTime(value)}`;
}

/** `S/ 12.50`, with a real minus sign for a negative amount: `−S/ 12.50`. */
export function formatSignedMoney(value: number): string {
  const text = NumberUtils.formatCurrency(Math.abs(value));
  return value < 0 ? `−${text}` : text;
}

export type ClosureOutcome = 'balanced' | 'shortage' | 'overage' | 'uncounted';

/** How a closing turned out, from counted minus expected (null when it was never counted). */
export function closureOutcome(difference: string | number | null | undefined): ClosureOutcome {
  if (difference === null || difference === undefined) return 'uncounted';
  const value = Number(difference);
  if (value === 0) return 'balanced';
  return value < 0 ? 'shortage' : 'overage';
}

export function closureText(difference: string | number | null | undefined): string {
  const outcome = closureOutcome(difference);
  if (outcome === 'balanced') return 'cuadrado, sin diferencia';
  if (outcome === 'shortage') return `faltante ${formatSignedMoney(Number(difference))}`;
  if (outcome === 'overage') return `sobrante ${formatSignedMoney(Number(difference))}`;
  return 'sin arqueo registrado';
}
