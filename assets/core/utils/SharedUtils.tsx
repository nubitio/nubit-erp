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

    static imageFormatter(imageUrl?: string, alt?: string) {
        if (imageUrl) {
            return (
                <a href={imageUrl} target="_blank" rel="noreferrer">
                    <img src={imageUrl} style={{width: '30px', height: '30px'}} alt={alt}/>
                </a>
            )
        } else {
            const name = alt ? alt : 'No image';
            const url = `https://ui-avatars.com/api/?name=${name}`;
            return <img src={url} style={{width: '30px', height: '30px'}} alt={alt ?? 'No image'}/>
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

    static validateRUC(ruc): boolean {
        let rucCopy = ruc;
        let validStartWith = [10, 15, 16, 17, 20];
        let rucPart = ruc.toString().substring(0, 2);
        if (ruc.length !== 11 || (!validStartWith.includes(parseInt(rucPart))))
            return false;

        let sum = 0;
        for (let i = 0; i < 11; i++) {
            if (i !== 0) {
                let factor = (i % 7 + (i / 7 | 0) + 1);
                sum += (ruc % 10) * factor;
            }
            ruc = ruc / 10 | 0
        }
        let mod = sum % 11;
        let res = 11 - mod;
        return (rucCopy % 10 === res % 10);
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
