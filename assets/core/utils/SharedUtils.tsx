import React from "react";
import notify from "devextreme/ui/notify";

export class SharedUtils {
    static booleanFormatter(value: boolean) {
        if (value) {
            return <span className="badge badge-outlined rounded-pill text-bg-primary">SI</span>;
        } else if (!value) {
            return <span className="badge badge-outlined rounded-pill text-bg-secondary">NO</span>;
        } else {
            return '';
        }
    }

    static dateFormatter(value: string) {
        const date = new Date(value);
        let timeOffset = date.getTimezoneOffset() * 60000;
        date.setTime(date.getTime() + timeOffset);
        return date.toLocaleDateString('es-PE', {timeZone: 'UTC'});
    }

    static timeFormatter(value: string) {
        const time = new Date(value);
        return time.toLocaleTimeString('es-PE', {timeZone: 'UTC'});
    }

    static dateTimeFormatter(value: string) {
        if (value === null || value === undefined || value === '') {
            return '';
        }
        const dateTime = new Date(value);
        const config: Intl.DateTimeFormatOptions = {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        };
        return new Intl.DateTimeFormat('es-PE', config).format(dateTime);
    }

    static generateId(dialog: string) {
        return `${dialog}-${Math.random().toString(36).substring(2, 9)}`;
    }
}

export default function toast(
    message: string,
    type: 'success' | 'error' | 'warning' | 'info' = 'info',
    time = 3000,
    position: 'top left' | 'top right' | 'bottom left' | 'bottom right' = 'top right'
) {
    notify({
        message: message,
        type: type,
        displayTime: time,
    }, {position: position})
}
