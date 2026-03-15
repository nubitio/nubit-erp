export const LIMA_TZ = 'America/Lima';

/** Extract date components in Lima timezone using Intl.DateTimeFormat. */
function limaDateParts(date: Date): { year: number; month: number; day: number } {
  const fmt = new Intl.DateTimeFormat('en-CA', {
    timeZone: LIMA_TZ,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  });
  const parts = fmt.formatToParts(date);
  const get = (type: string) => parseInt(parts.find((p) => p.type === type)!.value, 10);
  return { year: get('year'), month: get('month'), day: get('day') };
}

export class DateUtils {
  /**
   * Formats a Date as YYYY-MM-DD using America/Lima timezone.
   * Used for computation (API query params), not for direct display.
   */
  static dateFormatter(date: Date) {
    const { year: y, month: m, day: d } = limaDateParts(date);
    return y + '-' + (m < 10 ? '0' + m : m) + '-' + (d < 10 ? '0' + d : d);
  }

  static dateParser(dateString: string) {
    if (!dateString) return new Date();
    const dateParts = dateString.split('-');
    const y = parseInt(dateParts[0], 10);
    const m = parseInt(dateParts[1], 10);
    const d = parseInt(dateParts[2], 10);
    if (!isNaN(y) && !isNaN(m) && !isNaN(d)) {
      return new Date(y, m - 1, d);
    } else {
      return new Date();
    }
  }

  /** Display formatter — always uses America/Lima timezone. */
  static format(dateString: string, formatOptions?: Intl.DateTimeFormatOptions) {
    const date = new Date(dateString);
    if (!formatOptions) {
      formatOptions = { day: 'numeric', month: 'short', year: 'numeric' };
    }
    return date.toLocaleDateString('es-PE', { timeZone: LIMA_TZ, ...formatOptions });
  }

  static addDays(fieldValue: any, number: number): Date {
    const date = new Date(fieldValue);
    date.setDate(date.getDate() + number);
    return date;
  }

  static addMonths(fieldValue: any, number: number) {
    const date = new Date(fieldValue);
    date.setMonth(date.getMonth() + number);
    return date;
  }

  static addYears(fieldValue: any, number: number) {
    const date = new Date(fieldValue);
    date.setFullYear(date.getFullYear() + number);
    return date;
  }

  static dayDiff(startDate: any, endDate: any) {
    const dateStart = new Date(startDate);
    const dateEnd = new Date(endDate);
    const timeDiff = Math.abs(dateEnd.getTime() - dateStart.getTime());
    return Math.ceil(timeDiff / (1000 * 3600 * 24));
  }

  static monthDiff(startDate: any, endDate: any) {
    const dateStart = new Date(startDate);
    const dateEnd = new Date(endDate);
    let months = (dateEnd.getFullYear() - dateStart.getFullYear()) * 12;
    months -= dateStart.getMonth();
    months += dateEnd.getMonth();
    return months <= 0 ? 0 : months;
  }
}
