const MONTHS_ES = [
  'Enero',
  'Febrero',
  'Marzo',
  'Abril',
  'Mayo',
  'Junio',
  'Julio',
  'Agosto',
  'Septiembre',
  'Octubre',
  'Noviembre',
  'Diciembre',
];

function toNumber(value: unknown): number | null {
  if (value == null || value === '') return null;
  if (typeof value === 'number') return Number.isFinite(value) ? value : null;
  const parsed = Number(String(value).replace(/,/g, ''));
  return Number.isFinite(parsed) ? parsed : null;
}

/** Money for grids and owner screens: `S/ 12,838.40`. */
export function formatMoney(value: unknown, currency: string = 'PEN'): string {
  const amount = toNumber(value);
  if (amount === null) return '—';

  return new Intl.NumberFormat('es-PE', {
    style: 'currency',
    currency: currency || 'PEN',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount);
}

/**
 * Calendar date without clock time.
 * ISO datetimes are treated as date-only (no UTC→Lima shift that turns midnight into 7:00 p. m.).
 */
export function formatDate(value: unknown): string {
  if (value == null || value === '') return '';
  if (value instanceof Date) {
    const year = value.getFullYear();
    const month = String(value.getMonth() + 1).padStart(2, '0');
    const day = String(value.getDate()).padStart(2, '0');
    return `${day}/${month}/${year}`;
  }

  const raw = String(value).trim();
  const iso = /^(\d{4})-(\d{2})-(\d{2})/.exec(raw);
  if (iso) return `${iso[3]}/${iso[2]}/${iso[1]}`;

  const already = /^(\d{1,2})\/(\d{1,2})\/(\d{2,4})/.exec(raw);
  if (already) {
    const day = already[1].padStart(2, '0');
    const month = already[2].padStart(2, '0');
    const year = already[3].length === 2 ? `20${already[3]}` : already[3];
    return `${day}/${month}/${year}`;
  }

  return raw;
}

/** Payroll / accounting period: `Julio 2026`. */
export function formatMonthPeriod(value: unknown): string {
  if (value == null || value === '') return '';

  const raw = String(value).trim();
  const compact = /^(\d{4})(\d{2})$/.exec(raw);
  if (compact) {
    const month = Number(compact[2]);
    return `${MONTHS_ES[month - 1] ?? compact[2]} ${compact[1]}`;
  }

  const iso = /^(\d{4})-(\d{2})/.exec(raw);
  if (iso) {
    const month = Number(iso[2]);
    return `${MONTHS_ES[month - 1] ?? iso[2]} ${iso[1]}`;
  }

  const slash = /(\d{1,2})\/(\d{2,4})/.exec(raw);
  if (slash) {
    const month = Number(slash[1]);
    const year = slash[2].length === 2 ? `20${slash[2]}` : slash[2];
    if (month >= 1 && month <= 12) return `${MONTHS_ES[month - 1]} ${year}`;
  }

  const dated = /^(\d{4})-(\d{2})-(\d{2})/.exec(raw);
  if (dated) {
    const month = Number(dated[2]);
    return `${MONTHS_ES[month - 1] ?? dated[2]} ${dated[1]}`;
  }

  return formatDate(value);
}

export function padDocumentNumber(number: string | number | null | undefined, width = 8): string {
  const digits = String(number ?? '').replace(/\D/g, '');
  if (!digits) return String(number ?? '');
  return digits.padStart(width, '0');
}

/** `F001-1045` → `F001-00001045`. */
export function formatInvoiceNumber(code: unknown): string {
  const raw = String(code ?? '').trim();
  if (!raw) return '';
  const match = /^([A-Za-z]+\d*)-(\d+)$/.exec(raw);
  if (!match) return raw;
  return `${match[1].toUpperCase()}-${padDocumentNumber(match[2])}`;
}

export type ReceivableStatusKey = 'paid' | 'overdue' | 'partial' | 'pending';

export function parseDateOnly(value: unknown): Date | null {
  if (value == null || value === '') return null;
  const raw = String(value).trim();
  const iso = /^(\d{4})-(\d{2})-(\d{2})/.exec(raw);
  if (iso) return new Date(Number(iso[1]), Number(iso[2]) - 1, Number(iso[3]));
  const slash = /^(\d{1,2})\/(\d{1,2})\/(\d{2,4})/.exec(raw);
  if (slash) {
    const year = slash[3].length === 2 ? 2000 + Number(slash[3]) : Number(slash[3]);
    return new Date(year, Number(slash[2]) - 1, Number(slash[1]));
  }
  return null;
}

export function receivableStatus(row: {
  balanceDue?: unknown;
  paidTotal?: unknown;
  dueDate?: unknown;
}): ReceivableStatusKey {
  const balance = toNumber(row.balanceDue) ?? 0;
  const paid = toNumber(row.paidTotal) ?? 0;
  if (balance <= 0.009) return 'paid';
  const due = parseDateOnly(row.dueDate);
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  if (due && due < today) return 'overdue';
  if (paid > 0.009) return 'partial';
  return 'pending';
}

export const RECEIVABLE_STATUS_LABEL: Record<ReceivableStatusKey, string> = {
  paid: 'Cobrada',
  overdue: 'Vencida',
  partial: 'Parcial',
  pending: 'Pendiente',
};

export type PayableStatusKey = 'paid' | 'partial' | 'pending';

export function payableStatus(row: { balanceDue?: unknown; paidTotal?: unknown }): PayableStatusKey {
  const balance = toNumber(row.balanceDue) ?? 0;
  const paid = toNumber(row.paidTotal) ?? 0;
  if (balance <= 0.009) return 'paid';
  if (paid > 0.009) return 'partial';
  return 'pending';
}

export const PAYABLE_STATUS_LABEL: Record<PayableStatusKey, string> = {
  paid: 'Pagada',
  partial: 'Parcial',
  pending: 'Pendiente',
};

export function entityLabel(value: unknown, keys: string[] = ['businessName', 'name', 'label']): string {
  if (value == null || value === '') return '';
  if (typeof value === 'string') {
    if (value.startsWith('/api/')) return '';
    return value;
  }
  if (typeof value === 'object') {
    const record = value as Record<string, unknown>;
    for (const key of keys) {
      const part = record[key];
      if (typeof part === 'string' && part.trim() !== '') return part;
    }
  }
  return '';
}

export function startOfToday(): Date {
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  return today;
}

export function addDays(date: Date, days: number): Date {
  const next = new Date(date);
  next.setDate(next.getDate() + days);
  return next;
}

export function toIsoDate(date: Date): string {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}
