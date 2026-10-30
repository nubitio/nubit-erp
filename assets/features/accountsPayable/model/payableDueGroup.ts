import { DateTime } from 'luxon';

/** Calendar groups are relative to the report's business date, never UTC midnight. */
export function payableDueGroup(dueDate: unknown, asOf: string) {
  const today = DateTime.fromISO(asOf, { zone: 'America/Lima' }).startOf('day');
  const date =
    typeof dueDate === 'string'
      ? DateTime.fromISO(dueDate.slice(0, 10), { zone: 'America/Lima' })
      : null;
  if (!date?.isValid || !today.isValid)
    return { key: 'unknown', label: 'Sin vencimiento', overdue: false };
  if (date < today) return { key: 'overdue', label: 'Vencido', overdue: true };
  if (date <= today.endOf('week'))
    return { key: 'this-week', label: 'Esta semana', overdue: false };
  if (date <= today.plus({ weeks: 1 }).endOf('week'))
    return { key: 'next-week', label: 'Próxima semana', overdue: false };
  return {
    key: date.toFormat('yyyy-MM'),
    label: date.setLocale('es-PE').toFormat(date.year === today.year ? 'LLLL' : 'LLLL yyyy'),
    overdue: false,
  };
}
