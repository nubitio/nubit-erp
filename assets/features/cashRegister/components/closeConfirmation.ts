export interface CloseDifference {
  paymentMethodId: number;
  difference: number;
}

export interface CloseRow {
  paymentMethodId: number;
  paymentMethod?: string;
}

/** Cents-safe: differences are money, so anything under half a cent is a match. */
const isMatch = (difference: number) => Math.abs(difference) < 0.005;

const signed = (value: number, money: (n: number) => string) =>
  `${value > 0 ? '+' : value < 0 ? '−' : ''}${money(Math.abs(value))}`;

/**
 * What the user is told before closing the till: that the count matches, or which
 * payment methods are off and by how much, so a shortage is never closed by reflex.
 */
export function closeConfirmation(
  rows: readonly CloseRow[],
  differences: readonly CloseDifference[],
  money: (n: number) => string,
): { message: string; hasDifferences: boolean } {
  const off = differences.filter((entry) => !isMatch(entry.difference));
  if (off.length === 0) {
    return {
      hasDifferences: false,
      message: 'El conteo coincide con lo esperado. ¿Cerrar la caja?',
    };
  }

  const labels = off.map((entry) => {
    const name =
      rows.find((row) => row.paymentMethodId === entry.paymentMethodId)?.paymentMethod ??
      `Método ${entry.paymentMethodId}`;
    return `${name} ${signed(entry.difference, money)}`;
  });
  const total = off.reduce((sum, entry) => sum + entry.difference, 0);

  return {
    hasDifferences: true,
    message: `Hay diferencias: ${labels.join(', ')}. Total ${signed(total, money)}. Al cerrar quedan registradas. ¿Cerrar la caja de todos modos?`,
  };
}
