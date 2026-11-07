import { DateTime } from 'luxon';

/** The business day boundaries (and what the user sees) are in this zone; the API stores UTC. */
export const ACTIVITY_TIMEZONE = 'America/Lima';

export interface ActivityItem {
  id: number;
  /** ISO-8601 UTC, e.g. 2026-11-07T15:00:00Z */
  occurredAt: string;
  actor: string | null;
  type: string;
  sensitive: boolean;
  summary: string;
  detail: string | null;
  ip: string | null;
}

export type ActivityTypeFilter = 'sensitive' | 'all' | 'auth' | 'user' | 'role';
export type ActivityRange = 'today' | '7d' | '30d' | 'all';

export interface ActivityFilters {
  type: ActivityTypeFilter;
  actor: string;
  range: ActivityRange;
  search: string;
}

export const DEFAULT_FILTERS: ActivityFilters = {
  type: 'sensitive',
  actor: '',
  range: '7d',
  search: '',
};

export const TYPE_OPTIONS: { value: ActivityTypeFilter; label: string }[] = [
  { value: 'sensitive', label: 'Solo sensibles' },
  { value: 'all', label: 'Todos' },
  { value: 'auth', label: 'Ingresos y seguridad' },
  { value: 'user', label: 'Usuarios' },
  { value: 'role', label: 'Roles y permisos' },
];

export const RANGE_OPTIONS: { value: ActivityRange; label: string }[] = [
  { value: 'today', label: 'Hoy' },
  { value: '7d', label: 'Últimos 7 días' },
  { value: '30d', label: 'Últimos 30 días' },
  { value: 'all', label: 'Todo el registro' },
];

const zoned = (iso: string): DateTime =>
  DateTime.fromISO(iso, { zone: 'utc' }).setZone(ACTIVITY_TIMEZONE).setLocale('es');

export function formatTime(iso: string): string {
  const at = zoned(iso);
  return at.isValid ? at.toFormat('HH:mm') : '';
}

/** "HOY · SÁBADO 7 DE NOVIEMBRE", "AYER · VIERNES 6 DE NOVIEMBRE", or just the date for older days. */
export function dayHeading(day: DateTime, now: DateTime): string {
  const label = day.setLocale('es').toFormat("cccc d 'de' LLLL");
  const diff = now.setZone(ACTIVITY_TIMEZONE).startOf('day').diff(day.startOf('day'), 'days').days;
  const prefix = diff === 0 ? 'HOY · ' : diff === 1 ? 'AYER · ' : '';
  return (prefix + label).toLocaleUpperCase('es');
}

export interface ActivityGroup {
  /** YYYY-MM-DD in the business zone */
  key: string;
  heading: string;
  items: ActivityItem[];
}

/** Groups newest-first items by calendar day in the business zone, keeping their order. */
export function groupByDay(items: ActivityItem[], now: DateTime = DateTime.now()): ActivityGroup[] {
  const groups: ActivityGroup[] = [];
  for (const item of items) {
    const at = zoned(item.occurredAt);
    if (!at.isValid) continue;
    const key = at.toFormat('yyyy-MM-dd');
    const last = groups[groups.length - 1];
    if (last && last.key === key) {
      last.items.push(item);
    } else {
      groups.push({ key, heading: dayHeading(at, now), items: [item] });
    }
  }
  return groups;
}

/** Inclusive calendar-day bounds (YYYY-MM-DD, business zone) for a range choice; "all" has none. */
export function rangeToDates(
  range: ActivityRange,
  now: DateTime = DateTime.now(),
): { from?: string; to?: string } {
  if (range === 'all') return {};
  const today = now.setZone(ACTIVITY_TIMEZONE).startOf('day');
  const back = range === 'today' ? 0 : range === '7d' ? 6 : 29;
  return {
    from: today.minus({ days: back }).toFormat('yyyy-MM-dd'),
    to: today.toFormat('yyyy-MM-dd'),
  };
}

export function buildActivityQuery(
  filters: ActivityFilters,
  page?: number,
  now: DateTime = DateTime.now(),
): string {
  const params = new URLSearchParams({ type: filters.type });
  if (filters.actor) params.set('actor', filters.actor);
  if (filters.search.trim()) params.set('q', filters.search.trim());
  const { from, to } = rangeToDates(filters.range, now);
  if (from) params.set('from', from);
  if (to) params.set('to', to);
  if (page !== undefined) params.set('page', String(page));
  return params.toString();
}
