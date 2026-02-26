export class DateUtils {
  static dateFormatter(date: Date) {
    const y = date.getFullYear();
    const m = date.getMonth() + 1;
    const d = date.getDate();
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

  static format(dateString: string, formatOptions?: Intl.DateTimeFormatOptions) {
    const date = new Date(dateString);
    if (!formatOptions) {
      formatOptions = { day: 'numeric', month: 'short', year: 'numeric' };
    }
    return date.toLocaleDateString('es-PE', formatOptions);
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
