/**
 * What the collection dialog shows before the user confirms: how an amount spreads over the open invoices.
 * Mirrors the server's AllocationPlanner (oldest due date first) so the preview is what will happen; the server
 * stays the authority and its answer replaces this one on confirm. All arithmetic is in cents: no float drift.
 */

export interface OpenInvoice {
  id: string;
  /** ISO date; an invoice without one counts as due on its issue date. */
  dueDate: string | null;
  issuedDate: string | null;
  balanceDue: string;
}

export interface PlannedLine {
  invoiceId: string;
  /** Cents. */
  cents: number;
}

export interface Plan {
  lines: PlannedLine[];
  /** Cents the invoices cannot absorb: it stays as the customer's advance. */
  unappliedCents: number;
}

export const toCents = (value: string | number): number => {
  const n = typeof value === 'number' ? value : Number(String(value).replace(',', '.'));
  return Number.isFinite(n) ? Math.round(n * 100) : 0;
};

export const fromCents = (cents: number): string => (cents / 100).toFixed(2);

const effectiveDue = (invoice: OpenInvoice): string => invoice.dueDate ?? invoice.issuedDate ?? '';

/** Oldest due date first; ties by issue date then id, as the server does. */
export function planByDueDate(invoices: OpenInvoice[], amountCents: number): Plan {
  const ordered = [...invoices].sort((a, b) => {
    const keyA = [effectiveDue(a), a.issuedDate ?? '', a.id];
    const keyB = [effectiveDue(b), b.issuedDate ?? '', b.id];
    for (let i = 0; i < keyA.length; i += 1) {
      if (keyA[i] !== keyB[i]) return keyA[i] < keyB[i] ? -1 : 1;
    }
    return 0;
  });

  let remaining = Math.max(0, amountCents);
  const lines: PlannedLine[] = [];
  for (const invoice of ordered) {
    if (remaining <= 0) break;
    const take = Math.min(remaining, toCents(invoice.balanceDue));
    if (take <= 0) continue;
    lines.push({ invoiceId: invoice.id, cents: take });
    remaining -= take;
  }
  return { lines, unappliedCents: remaining };
}

/**
 * The user's own split. Returns an error message when it cannot be confirmed, null when it can.
 * A line may not exceed what its invoice owes, and the lines may not exceed the amount received.
 */
export function validateManual(
  invoices: OpenInvoice[],
  amountCents: number,
  lines: PlannedLine[],
): string | null {
  const owed = new Map(invoices.map((i) => [i.id, toCents(i.balanceDue)]));
  let total = 0;
  for (const line of lines) {
    if (line.cents < 0) return 'Los importes no pueden ser negativos.';
    if (line.cents > (owed.get(line.invoiceId) ?? 0)) {
      return 'Un importe supera lo que debe el comprobante.';
    }
    total += line.cents;
  }
  return total > amountCents ? 'La distribución supera el monto recibido.' : null;
}

export const manualPlan = (amountCents: number, lines: PlannedLine[]): Plan => ({
  lines: lines.filter((l) => l.cents > 0),
  unappliedCents: amountCents - lines.reduce((sum, l) => sum + l.cents, 0),
});

/** The tenders must add up to the amount received; returns the difference in cents (0 = balanced). */
export const tenderDifference = (amountCents: number, tenderCents: number[]): number =>
  amountCents - tenderCents.reduce((sum, c) => sum + c, 0);
