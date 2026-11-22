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

/** What the user chose to return of one line: the decimal text typed, and whether it goes back to stock. */
export interface ReturnChoice {
  quantity: string;
  restock: boolean;
}

/** Choices by lineNumber; a missing key means the line is not returned. */
export type Returns = Record<number, ReturnChoice>;

/** SUNAT reason 07 is the physical return where the user decides what re-enters the warehouse. */
export const choosesRestock = (code: string) => code === '07';

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
    const choice = returns[line.lineNumber];
    return choice !== undefined && isValidReturnQuantity(line, choice.quantity);
  });
}

/** What checking a line returns at first: one unit, or everything sold when less than one was (0.5 kg). */
export function defaultReturnQuantity(line: OriginLine): string {
  const sold = toMicro(line.quantity);
  return sold !== null && sold < 10n ** BigInt(MICRO) ? line.quantity : '1';
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
  return Object.fromEntries(
    lines.map((line) => [line.lineNumber, { quantity: line.quantity, restock: true }]),
  );
}

/**
 * Display-only estimate: the server recalculates every amount from the affected
 * document when the note is saved. Tax is backed out of the gross total.
 */
export function returnSummary(lines: readonly OriginLine[], returns: Returns) {
  let total = 0n;
  let taxable = 0n;
  for (const line of selectedLines(lines, returns)) {
    const cents = lineReturnCents(line, returns[line.lineNumber]!.quantity) ?? 0n;
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
      const choice = input.returns[line.lineNumber]!;
      return { ...copy, quantity: choice.quantity, restock: choice.restock };
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

/** The compact form the draft and preview endpoints take: choices only, never amounts. */
export function buildReturnChoices(lines: readonly OriginLine[], returns: Returns) {
  return selectedLines(lines, returns).map((line) => ({
    lineNumber: line.lineNumber,
    quantity: returns[line.lineNumber]!.quantity,
    restock: returns[line.lineNumber]!.restock,
  }));
}

/** Restores the choices of a saved draft onto the lines the origin document has today. */
export function returnsFromDraft(
  lines: readonly OriginLine[],
  saved: readonly { lineNumber: number; quantity: string; restock: boolean }[],
): Returns {
  const known = new Set(lines.map((line) => line.lineNumber));
  return Object.fromEntries(
    saved
      .filter((row) => known.has(row.lineNumber))
      .map((row) => [row.lineNumber, { quantity: row.quantity, restock: row.restock }]),
  );
}

/** Zero-pads the correlative the way it is printed: 58 → 00000058. */
export const padNumber = (number: string) => number.padStart(8, '0');
