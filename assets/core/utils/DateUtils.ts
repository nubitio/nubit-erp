export class DateUtils {
    static dateFormatter(date: Date) {
        const y = date.getFullYear();
        const m = date.getMonth() + 1;
        const d = date.getDate();
        return y + '-' + (m < 10 ? ('0' + m) : m) + '-' + (d < 10 ? ('0' + d) : d);
    }

    static dateParser(dateString: string) {
        if (!dateString) return new Date();
        const dateParts = (dateString.split('-'));
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
        console.log(date);
        date.setHours(date.getHours() + 5);
        console.log(date);
        if (!formatOptions) {
            formatOptions = {day: 'numeric', month: 'short', year: 'numeric'};
        }
        return date.toLocaleDateString('es-PE', formatOptions);
    }
}