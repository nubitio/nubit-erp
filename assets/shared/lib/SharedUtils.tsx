import DOMPurify from 'dompurify';
import React from 'react';
import { Badge } from '@nubitio/ui';
import type { DataRecord } from '@nubitio/core';

type ToastType = 'success' | 'error' | 'warning' | 'info';
type ToastPosition = 'top left' | 'top right' | 'bottom left' | 'bottom right';

const TOAST_STYLE_ID = 'app-toast-styles';

function ensureToastStyles() {
  if (typeof document === 'undefined' || document.getElementById(TOAST_STYLE_ID)) {
    return;
  }

  const style = document.createElement('style');
  style.id = TOAST_STYLE_ID;
  style.textContent = `
    .app-toast-container {
      position: fixed;
      z-index: 2147483647;
      display: flex;
      flex-direction: column;
      gap: 8px;
      max-width: min(380px, calc(100vw - 32px));
      pointer-events: none;
    }
    .app-toast-container--top-right { top: 16px; right: 16px; align-items: flex-end; }
    .app-toast-container--top-left { top: 16px; left: 16px; align-items: flex-start; }
    .app-toast-container--bottom-right { right: 16px; bottom: 16px; align-items: flex-end; }
    .app-toast-container--bottom-left { left: 16px; bottom: 16px; align-items: flex-start; }
    .app-toast {
      pointer-events: auto;
      border: 1px solid var(--border-color, rgba(15, 23, 42, 0.12));
      border-left-width: 4px;
      border-radius: var(--radius-md, 12px);
      background: var(--surface-1, #fff);
      color: var(--text-primary, #0f172a);
      box-shadow: 0 16px 40px rgba(15, 23, 42, 0.18);
      padding: 12px 14px;
      font-size: 14px;
      line-height: 1.4;
      opacity: 0;
      transform: translateY(-6px);
      transition: opacity 160ms ease, transform 160ms ease;
    }
    .app-toast--visible { opacity: 1; transform: translateY(0); }
    .app-toast--success { border-left-color: #16a34a; }
    .app-toast--error { border-left-color: #dc2626; }
    .app-toast--warning { border-left-color: #d97706; }
    .app-toast--info { border-left-color: #2563eb; }
  `;
  document.head.appendChild(style);
}

function getToastContainer(position: ToastPosition) {
  ensureToastStyles();
  const positionClass = position.replace(' ', '-');
  const id = `app-toast-container-${positionClass}`;
  let container = document.getElementById(id);
  if (container) {
    return container;
  }

  container = document.createElement('div');
  container.id = id;
  container.className = `app-toast-container app-toast-container--${positionClass}`;
  document.body.appendChild(container);
  return container;
}

const LOCAL_AVATAR_COLORS = [
  '#4a6fa5',
  '#3d8a74',
  '#7a6099',
  '#4a8ab5',
  '#6a7f5e',
  '#a06050',
  '#526a7d',
  '#7d7040',
];

export class SharedUtils {
  private static getAvatarColor(name: string): string {
    let hash = 0;
    for (let index = 0; index < name.length; index++) {
      hash = (hash * 31 + name.charCodeAt(index)) & 0xfffffff;
    }

    return LOCAL_AVATAR_COLORS[hash % LOCAL_AVATAR_COLORS.length];
  }

  private static getAvatarInitials(name: string): string {
    const normalizedName = name.trim();
    if (!normalizedName) {
      return 'NA';
    }

    const words = normalizedName.split(/\s+/);
    if (words.length >= 2) {
      return `${words[0][0]}${words[1][0]}`.toUpperCase();
    }

    return normalizedName.slice(0, 2).toUpperCase();
  }

  static booleanFormatter(value: boolean) {
    if (value) {
      return <Badge variant="primary" outlined>SI</Badge>;
    } else if (!value) {
      return <Badge variant="secondary" outlined>NO</Badge>;
    } else {
      return '';
    }
  }

  static imageFormatter(imageUrl?: string, alt?: string) {
    if (imageUrl) {
      return (
        <div className="flex items-center justify-center">
          <a href={imageUrl} target="_blank" rel="noreferrer">
            <img
              src={imageUrl}
              className="object-cover"
              style={{ width: '30px', height: '30px' }}
              alt={alt}
            />
          </a>
        </div>
      );
    } else {
      const name = alt ? alt : 'No image';
      const avatarColor = SharedUtils.getAvatarColor(name);
      const avatarInitials = SharedUtils.getAvatarInitials(name);

      return (
        <div className="flex items-center justify-center">
          <div
            style={{
              width: '30px',
              height: '30px',
              borderRadius: '50%',
              background: avatarColor,
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              color: 'rgba(255, 255, 255, 0.92)',
              fontSize: '12px',
              fontWeight: 700,
              letterSpacing: '-0.02em',
              userSelect: 'none',
              textShadow: '0 1px 2px rgba(0, 0, 0, 0.24)',
            }}
            aria-label={alt ?? 'No image'}
            title={alt ?? 'No image'}
          >
            {avatarInitials}
          </div>
        </div>
      );
    }
  }

  static dateFormatter(value: Date | string | null | undefined) {
    if (value === null || value === undefined || value === '') return '';
    if (value instanceof Date) {
      return value.toLocaleDateString('es-PE', { timeZone: 'America/Lima' });
    }
    const dateStr = value.trim();

    // ISO datetime string (e.g. "2026-05-20T00:00:00+00:00"): these fields are
    // conceptually date-only, stored as midnight UTC. A UTC→Lima conversion would
    // shift midnight back to the previous day. Extract the date portion directly
    // from the string instead.
    if (/^\d{4}-\d{2}-\d{2}T/.test(dateStr)) {
      const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(dateStr);
      if (m) return `${m[3]}/${m[2]}/${m[1]}`;
    }

    // YYYY-MM-DD (date-only, no timezone info — treat as local Lima date as-is)
    let m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(dateStr);
    if (m) return `${m[3]}/${m[2]}/${m[1]}`;

    // DD/MM/YYYY (already formatted)
    m = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(dateStr);
    if (m) return dateStr;
  }

  static timeFormatter(value: string) {
    const time = new Date(value);
    return time.toLocaleTimeString('es-PE', { timeZone: 'America/Lima' });
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
      timeZone: 'America/Lima',
    };
    return new Intl.DateTimeFormat('es-PE', config).format(dateTime);
  }

  static generateId(dialog: string) {
    return `${dialog}-${Math.random().toString(36).substring(2, 9)}`;
  }

  static validateRUC(ruc: string | number): boolean {
    const validStartWith = [10, 15, 16, 17, 20];
    const rucString = String(ruc);
    const rucPart = rucString.substring(0, 2);
    if (rucString.length !== 11 || !validStartWith.includes(parseInt(rucPart, 10))) return false;

    const digits = rucString.split('').map((digit) => Number(digit));
    let sum = 0;
    for (let i = 0; i < 11; i++) {
      if (i !== 0) {
        const factor = (i % 7) + ((i / 7) | 0) + 1;
        sum += digits[10 - i] * factor;
      }
    }
    const mod = sum % 11;
    const res = 11 - mod;
    return digits[10] === res % 10;
  }

  static tagsFormatter(value: DataRecord[] | null | undefined, name: string) {
    if (value && value.length > 0) {
      return (
        <div className="flex flex-wrap">
          {value.map((item: DataRecord, index) => {
            const key =
              typeof item.id === 'string' || typeof item.id === 'number' ? item.id : index;
            const label =
              typeof item[name] === 'string' || typeof item[name] === 'number' ? item[name] : '';

            return (
              <Badge key={key} variant="primary" outlined className="me-1 mb-1">
                {label}
              </Badge>
            );
          })}
        </div>
      );
    } else {
      return '';
    }
  }

  static htmlFormatter(value: string | null | undefined) {
    if (value) {
      return (
        <div className="text-truncate" style={{ maxWidth: '200px' }}>
          <span dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(value) }} />
        </div>
      );
    } else {
      return '';
    }
  }

  static billStatusFormatter(value: string | number | null | undefined) {
    const raw = value === null || value === undefined || value === '' ? -1 : Number(value);
    const code = Number.isNaN(raw) ? -1 : raw;
    if (code === -1) {
      return <Badge variant="info" outlined>PENDIENTE</Badge>;
    } else if (code === 0) {
      return <Badge variant="success" outlined>ENVIADO</Badge>;
    } else if (code < 2000) {
      return <Badge variant="warning" outlined>ERRONEO</Badge>;
    } else if (code >= 2000 && code < 4000) {
      return <Badge variant="danger" outlined>RECHAZADO</Badge>;
    } else if (code >= 4000) {
      return <Badge variant="secondary" outlined>OBSERVADO</Badge>;
    }
  }

  static saleStatusFormatter(sale: DataRecord | null | undefined) {
    const isVoided = sale?.estado === 2 || sale?.invoiceVoided === 2 || sale?.status === 'VOIDED';
    if (isVoided) {
      return <Badge variant="danger" outlined>ANULADO</Badge>;
    }

    const hasInvoice = !!sale?.invoice || !!sale?.invoiceFullNumber;
    if (!hasInvoice) {
      return <Badge variant="success" outlined>ACEPTADO</Badge>;
    }

    const rawStatus = sale?.invoiceSunatStatus ?? sale?.sunatStatus;
    if (rawStatus === null || rawStatus === undefined || rawStatus === '') {
      return <Badge variant="info" outlined>PENDIENTE</Badge>;
    }

    const statusNumber = Number(rawStatus);
    if (Number.isNaN(statusNumber)) {
      return <Badge variant="primary" outlined>PENDIENTE</Badge>;
    }

    if (statusNumber === 0) {
      return <Badge variant="success" outlined>ACEPTADO</Badge>;
    }

    if (statusNumber < 2000) {
      return <Badge variant="warning" outlined>ERRONEO</Badge>;
    }

    if (statusNumber < 4000) {
      return <Badge variant="danger" outlined>RECHAZADO</Badge>;
    }

    return <Badge variant="secondary" outlined>OBSERVADO</Badge>;
  }
}

export default function toast(
  message: string,
  type: ToastType = 'info',
  time = 3000,
  position: ToastPosition = 'top right',
) {
  if (typeof document === 'undefined') {
    return;
  }

  const container = getToastContainer(position);
  const element = document.createElement('div');
  element.className = `app-toast app-toast--${type}`;
  element.setAttribute('role', type === 'error' || type === 'warning' ? 'alert' : 'status');
  element.textContent = message;
  container.appendChild(element);

  window.requestAnimationFrame(() => element.classList.add('app-toast--visible'));
  window.setTimeout(() => {
    element.classList.remove('app-toast--visible');
    window.setTimeout(() => {
      element.remove();
      if (container.childElementCount === 0) {
        container.remove();
      }
    }, 180);
  }, time);
}

export const getIRI = (url: string, value: string) => {
  if (value.startsWith('/')) {
    return value;
  }

  return url + '/' + value;
};
