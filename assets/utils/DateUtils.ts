export class DateUtils {
    static dateFormatter(date) {
        const y = date.getFullYear();
        const m = date.getMonth() + 1;
        const d = date.getDate();
        return y + '-' + (m < 10 ? ('0' + m) : m) + '-' + (d < 10 ? ('0' + d) : d);
    }

    static dateParser(dateString) {
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
}