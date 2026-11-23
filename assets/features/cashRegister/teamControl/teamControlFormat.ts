export type TeamControlRange = '7d' | '30d' | 'month';
export type MemberLevel = 'ok' | 'review';

export interface MemberMetrics {
  salesCount: number;
  salesTotal: number;
  subtotal: number;
  discountTotal: number;
  voidCount: number;
  voidTotal: number;
  creditNoteCount: number;
  creditNoteTotal: number;
  cashSessions: number;
  cashShortCount: number;
  cashShortage: number;
  cashSurplus: number;
  manualOutflowCount: number;
  manualOutflowTotal: number;
}

export interface MemberSignal {
  key: string;
  label: string;
  detail: string;
}

export interface TeamMember {
  userId: number;
  name: string;
  metrics: MemberMetrics;
  voidRate: number;
  creditNoteRate: number;
  discountRate: number;
  level: MemberLevel;
  signals: MemberSignal[];
}

export interface CashDifference {
  sessionId: number;
  userId: number;
  name: string;
  closedAt: string;
  expected: number;
  counted: number;
  difference: number;
}

export interface TeamControlOverview {
  period: { from: string; to: string };
  cashTolerance: number;
  team: { salesCount: number; salesTotal: number; membersToReview: number };
  members: TeamMember[];
  unattributed: {
    voidCount: number;
    voidTotal: number;
    creditNoteCount: number;
    creditNoteTotal: number;
  };
  cashDifferences: CashDifference[];
}

export type MemberEventType =
  'sale_voided' | 'credit_note' | 'discount' | 'cash_difference' | 'manual_outflow';

export interface MemberEvent {
  type: MemberEventType;
  at: string;
  reference: string | null;
  amount: number;
  note: string | null;
  saleId: string | null;
}

export const RANGE_OPTIONS: { value: TeamControlRange; label: string }[] = [
  { value: '7d', label: 'Últimos 7 días' },
  { value: '30d', label: 'Últimos 30 días' },
  { value: 'month', label: 'Este mes' },
];

export const EVENT_LABELS: Record<MemberEventType, string> = {
  sale_voided: 'Venta anulada',
  credit_note: 'Nota de crédito',
  discount: 'Descuento',
  cash_difference: 'Diferencia al cerrar caja',
  manual_outflow: 'Retiro o gasto de caja',
};

const pad = (n: number) => String(n).padStart(2, '0');
export const toIsoDate = (date: Date) =>
  `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;

/** The from/to (inclusive, YYYY-MM-DD) a range option stands for, counted back from `today`. */
export function rangeToDates(range: TeamControlRange, today: Date = new Date()) {
  const to = new Date(today.getFullYear(), today.getMonth(), today.getDate());
  const from = new Date(to);
  if (range === 'month') {
    from.setDate(1);
  } else {
    from.setDate(from.getDate() - (range === '30d' ? 29 : 6));
  }
  return { from: toIsoDate(from), to: toIsoDate(to) };
}

export function formatPercent(rate: number): string {
  return `${(rate * 100).toFixed(1)}%`;
}

/** "2026-11-22 14:05:00" → "22/11 14:05". The server sends local business time. */
export function formatEventTime(value: string): string {
  const match = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/.exec(value);
  return match ? `${match[3]}/${match[2]} ${match[4]}:${match[5]}` : value;
}

/** A closing difference reads "falta" when negative and "sobra" when positive. */
export function describeDifference(difference: number): 'faltante' | 'sobrante' | 'exacto' {
  if (difference < 0) return 'faltante';
  if (difference > 0) return 'sobrante';
  return 'exacto';
}
