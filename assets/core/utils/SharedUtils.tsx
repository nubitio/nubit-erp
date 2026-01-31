import React from 'react';
import notify from 'devextreme/ui/notify';

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
        <div className="d-flex align-items-center justify-content-center">
          <a href={imageUrl} target="_blank" rel="noreferrer">
            <img
              src={imageUrl}
              className="object-cover"
              style={{width: '30px', height: '30px'}}
              alt={alt}
            />
          </a>
        </div>
      );
    } else {
      const name = alt ? alt : 'No image';
      const url = `https://ui-avatars.com/api/?name=${name}`;
      return (
        <div className="d-flex align-items-center justify-content-center">
          <img
            src={url}
            className="object-cover"
            style={{width: '30px', height: '30px'}}
            alt={alt ?? 'No image'}
          />
        </div>
      );
    }
  }

  static dateFormatter(value: Date | string | null | undefined) {
    if (value === null || value === undefined || value === '') return '';
    if (value instanceof Date) {
      return value.toLocaleDateString('es-PE', {timeZone: 'UTC'});
    }
    const dateStr = value.trim();
    if (/^\d{4}-\d{2}-\d{2}T/.test(dateStr)) {
      const ymd = dateStr.slice(0, 10);
      const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(ymd);
      if (m) return `${m[3]}/${m[2]}/${m[1]}`;
    }

    // YYYY-MM-DD (date-only)
    let m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(dateStr);
    if (m) return `${m[3]}/${m[2]}/${m[1]}`;

    // DD/MM/YYYY (already formatted)
    m = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(dateStr);
    if (m) return dateStr;
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
      minute: '2-digit',
    };
    return new Intl.DateTimeFormat('es-PE', config).format(dateTime);
  }

  static generateId(dialog: string) {
    return `${dialog}-${Math.random().toString(36).substring(2, 9)}`;
  }

  static validateRUC(ruc: any): boolean {
    const rucCopy = ruc;
    const validStartWith = [10, 15, 16, 17, 20];
    const rucPart = ruc.toString().substring(0, 2);
    if (ruc.length !== 11 || !validStartWith.includes(parseInt(rucPart))) return false;

    let sum = 0;
    for (let i = 0; i < 11; i++) {
      if (i !== 0) {
        const factor = (i % 7) + ((i / 7) | 0) + 1;
        sum += (ruc % 10) * factor;
      }
      ruc = (ruc / 10) | 0;
    }
    const mod = sum % 11;
    const res = 11 - mod;
    return rucCopy % 10 === res % 10;
  }

  static tagsFormatter(value: any, name: string) {
    if (value && value.length > 0) {
      return (
        <div className="d-flex flex-wrap">
          {value.map((item: any) => (
            <span
              key={item.id}
              className="badge badge-outlined rounded-pill text-bg-primary me-1 mb-1"
            >
              {item[name]}
            </span>
          ))}
        </div>
      );
    } else {
      return '';
    }
  }

  static htmlFormatter(value: any) {
    if (value) {
      return (
        <div className="text-truncate" style={{maxWidth: '200px'}}>
          <span dangerouslySetInnerHTML={{__html: value}}/>
        </div>
      );
    } else {
      return '';
    }
  }

  static billStatusFormatter(value: any) {
    if (value === null || value === undefined || value === '') {
      value = -1;
    } else {
      value = Number(value);
    }
    if (value === -1) {
      return (
        <span className="badge badge-outlined rounded-pill text-bg-primary">PENDIENTE</span>
      );
    } else if (value === 0) {
      return <span className="badge badge-outlined rounded-pill text-bg-success">ENVIADO</span>;
    } else if (value < 2000) {
      return <span className="badge badge-outlined rounded-pill text-bg-warning">ERRONEO</span>;
    } else if (value >= 2000 && value < 4000) {
      return (
        <span className="badge badge-outlined rounded-pill text-bg-danger">RECHAZADO</span>
      );
    } else if (value >= 4000) {
      return <span className="badge badge-outlined rounded-pill text-bg-info">OBSERVADO</span>;
    }
  }

  static saleStatusFormatter(sale: any) {
    const isVoided = sale?.estado === 2 || sale?.invoiceVoided === 2;
    if (isVoided) {
      return <span className="badge badge-outlined rounded-pill text-bg-danger">ANULADO</span>;
    }

    const hasInvoice = !!sale?.invoice;
    if (!hasInvoice) {
      return <span className="badge badge-outlined rounded-pill text-bg-success">ACEPTADO</span>;
    }

    const rawStatus = sale?.invoiceSunatStatus;
    if (rawStatus === null || rawStatus === undefined || rawStatus === '') {
      return <span className="badge badge-outlined rounded-pill text-bg-primary">PENDIENTE</span>;
    }

    const statusNumber = Number(rawStatus);
    if (Number.isNaN(statusNumber)) {
      return <span className="badge badge-outlined rounded-pill text-bg-primary">PENDIENTE</span>;
    }

    if (statusNumber === 0) {
      return <span className="badge badge-outlined rounded-pill text-bg-success">ACEPTADO</span>;
    }

    if (statusNumber < 2000) {
      return <span className="badge badge-outlined rounded-pill text-bg-warning">ERRONEO</span>;
    }

    if (statusNumber < 4000) {
      return <span className="badge badge-outlined rounded-pill text-bg-danger">RECHAZADO</span>;
    }

    return <span className="badge badge-outlined rounded-pill text-bg-info">OBSERVADO</span>;
  }
}

export default function toast(
  message: string,
  type: 'success' | 'error' | 'warning' | 'info' = 'info',
  time = 3000,
  position: 'top left' | 'top right' | 'bottom left' | 'bottom right' = 'top right',
) {
  notify(
    {
      message: message,
      type: type,
      displayTime: time,
    },
    {position: position},
  );
}

export const getIRI = (url: string, value: string) => {
  if (value.startsWith('/')) {
    return value;
  }

  return url + '/' + value;
};
