/** Catalog 09 (SUNAT) reasons. Codes 01, 02 and 06 annul the affected document. */
export const CREDIT_NOTE_REASONS = [
  { value: '01', text: 'Anulación de la operación' },
  { value: '02', text: 'Anulación por error en el RUC' },
  { value: '03', text: 'Corrección por error en la descripción' },
  { value: '04', text: 'Descuento global' },
  { value: '05', text: 'Descuento por ítem' },
  { value: '06', text: 'Devolución total' },
  { value: '07', text: 'Devolución por ítem' },
  { value: '08', text: 'Bonificación' },
  { value: '09', text: 'Disminución en el valor' },
  { value: '10', text: 'Otros conceptos' },
  { value: '11', text: 'Ajustes de operaciones de exportación' },
  { value: '12', text: 'Ajustes afectos al IVAP' },
] as const;

export const FREQUENT_REASON_CODES = ['01', '06', '07', '05', '03'] as const;

const ANNULLING_CODES: readonly string[] = ['01', '02', '06'];

export const annulsDocument = (code: string) => ANNULLING_CODES.includes(code);

/** Annulling reasons return every line in full, so the user cannot pick quantities. */
export const returnsEverything = (code: string) => annulsDocument(code);

export interface OriginLine {
  lineNumber: number;
  productCode?: string | null;
  productName: string;
  quantity: string;
  unitPrice: string;
  taxPercentage?: string | null;
  total: string;
  [key: string]: unknown;
}

/** quantity by lineNumber, as the decimal text the user typed. */
export type Returns = Record<number, string>;

const MICRO = 6;

/** Parse a non-negative decimal into micro-units (1e-6); null when invalid. */
function toMicro(value: string | number | null | undefined): bigint | null {
  const text = String(value ?? '').trim();
  const match = /^(\d+)(?:\.(\d+))?$/.exec(text);
  if (!match) return null;
  const fraction = (match[2] ?? '').slice(0, MICRO).padEnd(MICRO, '0');
  return BigInt(match[1]!) * 10n ** BigInt(MICRO) + BigInt(fraction);
}

function formatCents(cents: bigint): string {
  const whole = cents / 100n;
  const rest = (cents % 100n).toString().padStart(2, '0');
  return `${whole}.${rest}`;
}

/** unitPrice × quantity in cents, rounded half-up; null when an input is invalid. */
export function lineReturnCents(line: OriginLine, quantity: string): bigint | null {
  const price = toMicro(line.unitPrice);
  const qty = toMicro(quantity);
  if (price === null || qty === null) return null;
  const scale = 10n ** BigInt(MICRO * 2 - 2);
  return (price * qty + scale / 2n) / scale;
}

export function isValidReturnQuantity(line: OriginLine, quantity: string): boolean {
  const qty = toMicro(quantity);
  const max = toMicro(line.quantity);
  return qty !== null && max !== null && qty > 0n && qty <= max;
}

export function selectedLines(lines: readonly OriginLine[], returns: Returns) {
  return lines.filter((line) => {
    const qty = returns[line.lineNumber];
    return qty !== undefined && isValidReturnQuantity(line, qty);
  });
}

/** What checking a line returns at first: one unit, or everything sold when less than one was (0.5 kg). */
export function defaultReturnQuantity(line: OriginLine): string {
  const sold = toMicro(line.quantity);
  return sold !== null && sold < 10n ** BigInt(MICRO) ? line.quantity : '1';
}

/**
 * The quantity after pressing + or −, or null when it reaches zero (the line is unchecked).
 * Moves by one but never past what was sold, so a 0.5 line reaches 0.5 and 2.5 reaches 2.5.
 */
export function stepReturnQuantity(
  line: OriginLine,
  current: string | undefined,
  delta: 1 | -1,
): string | null {
  const max = toMicro(line.quantity);
  const now = toMicro(current ?? '0');
  if (max === null || now === null) return null;
  const one = 10n ** BigInt(MICRO);
  let next = now + (delta === 1 ? one : -one);
  if (next > max) next = max;
  if (next <= 0n) return null;
  const whole = next / one;
  const fraction = (next % one).toString().padStart(MICRO, '0').replace(/0+$/, '');
  return fraction ? `${whole}.${fraction}` : String(whole);
}

/** Why a credit note cannot be issued against this document, or null when it can. */
export function creditNoteBlockedReason(origin: {
  voided?: boolean;
  stage: 'accepted' | 'rejected' | 'observed' | 'pending' | 'sent' | 'error';
}): string | null {
  if (origin.voided) return 'Este comprobante ya fue anulado.';
  if (origin.stage === 'accepted') return null;
  if (origin.stage === 'rejected')
    return 'SUNAT rechazó este comprobante. Reemplázalo en lugar de emitir una nota de crédito.';
  return 'SUNAT aún no aceptó este comprobante. Una nota de crédito solo se emite sobre uno aceptado.';
}

export function returnAllLines(lines: readonly OriginLine[]): Returns {
  return Object.fromEntries(lines.map((line) => [line.lineNumber, line.quantity]));
}

/**
 * Display-only estimate: the server recalculates every amount from the affected
 * document when the note is saved. Tax is backed out of the gross total.
 */
export function returnSummary(lines: readonly OriginLine[], returns: Returns) {
  let total = 0n;
  let taxable = 0n;
  for (const line of selectedLines(lines, returns)) {
    const cents = lineReturnCents(line, returns[line.lineNumber]!) ?? 0n;
    const percent = toMicro(line.taxPercentage ?? '0') ?? 0n;
    const divisor = 100n * 10n ** BigInt(MICRO) + percent;
    const base = (cents * 100n * 10n ** BigInt(MICRO) * 2n + divisor) / (divisor * 2n);
    total += cents;
    taxable += base;
  }
  return {
    count: selectedLines(lines, returns).length,
    total: formatCents(total),
    valueSold: formatCents(taxable),
    tax: formatCents(total - taxable),
  };
}

export interface CreditNotePayloadInput {
  relatedTaxDocumentIri: string;
  issueDate: string;
  reasonCode: string;
  reasonDescription: string;
  lines: readonly OriginLine[];
  returns: Returns;
}

const OMITTED_LINE_FIELDS = ['id', 'taxDocument', 'createdAt', '@id', '@type', '_iri'];

/** Body for POST /tax-documents; the server re-derives every amount from the origin lines. */
export function buildCreditNotePayload(input: CreditNotePayloadInput) {
  return {
    relatedTaxDocument: input.relatedTaxDocumentIri,
    issueDate: input.issueDate,
    reasonCode: input.reasonCode,
    reasonDescription: input.reasonDescription.trim(),
    lines: selectedLines(input.lines, input.returns).map((line) => {
      const copy = Object.fromEntries(
        Object.entries(line).filter(([key]) => !OMITTED_LINE_FIELDS.includes(key)),
      );
      return { ...copy, quantity: input.returns[line.lineNumber]! };
    }),
  };
}

export function creditNoteIssueErrors(input: {
  reasonCode: string;
  reasonDescription: string;
  lines: readonly OriginLine[];
  returns: Returns;
}) {
  const errors: { reason?: string; description?: string; lines?: string } = {};
  if (!input.reasonCode) errors.reason = 'Elige el tipo de nota de crédito.';
  if (!input.reasonDescription.trim())
    errors.description = 'Explica el motivo; aparece en la nota.';
  if (selectedLines(input.lines, input.returns).length === 0)
    errors.lines = 'Marca al menos un ítem con una cantidad válida.';
  return errors;
}
