import { Constants } from '@platform/common/Constants';
import type { PaymentMethodOption, PosItem } from './types';

export const IGV_RATE = 0.18;
export const CUSTOMER_PAGE_SIZE = 50;

export const normalizeText = (value: string) => value.toLowerCase().trim();

export const toNumber = (value: unknown, fallback = 0): number => {
  if (typeof value === 'number' && Number.isFinite(value)) {
    return value;
  }

  const parsed = Number.parseFloat(String(value ?? ''));
  return Number.isFinite(parsed) ? parsed : fallback;
};

export const toOptionalNumber = (value: unknown): number | undefined => {
  if (value === null || value === undefined || value === '') {
    return undefined;
  }

  const parsed = Number.parseFloat(String(value));
  return Number.isFinite(parsed) ? parsed : undefined;
};

export const normalizeProduct = (raw: any): PosItem => ({
  ...raw,
  id: String(raw?.id ?? ''),
  name: String(raw?.name ?? 'Producto'),
  salePrice: toNumber(raw?.salePrice ?? raw?.precio_unitario ?? raw?.price, 0),
  stock: toOptionalNumber(raw?.stock),
  sku: raw?.sku ? String(raw.sku) : undefined,
  barcode: raw?.barcode ? String(raw.barcode) : undefined,
  category:
    raw?.category && raw.category.id
      ? {
          id: String(raw.category.id),
          name: String(raw.category.name ?? 'Sin categoria'),
          icon: raw.category.icon,
          active: raw.category.active,
        }
      : {
          id: 'uncategorized',
          name: 'Sin categoria',
        },
});

export const extractCollection = <T,>(raw: any): T[] => {
  if (Array.isArray(raw)) {
    return raw;
  }
  if (Array.isArray(raw?.['hydra:member'])) {
    return raw['hydra:member'];
  }
  if (Array.isArray(raw?.items)) {
    return raw.items;
  }
  if (Array.isArray(raw?.data)) {
    return raw.data;
  }
  return [];
};

export const toLocalIsoString = (date: Date) => {
  const copy = new Date(date);
  const timeOffset = copy.getTimezoneOffset() * 60000;
  copy.setTime(copy.getTime() - timeOffset);
  return copy.toISOString();
};

export const isCashPaymentMethod = (method: PaymentMethodOption | null | undefined) => {
  if (!method) {
    return false;
  }

  const name = normalizeText(method.name);
  return method.id === 1 || name.includes('efect');
};

export const buildCustomersUrl = (query: string, page: number) => {
  const params = new URLSearchParams();
  params.set('itemsPerPage', String(CUSTOMER_PAGE_SIZE));
  params.set('page', String(page));
  params.append('searchExpr[]', 'businessName');
  params.append('searchExpr[]', 'ruc');

  const trimmed = query.trim();
  if (trimmed) {
    params.set('searchValue', trimmed);
  }

  return `${Constants.API_BASE_URL}customers?${params.toString()}`;
};

export const parseBooleanSetting = (value: unknown, fallback: boolean): boolean => {
  if (typeof value === 'boolean') {
    return value;
  }

  const normalized = String(value ?? '')
    .trim()
    .toLowerCase();

  if (['true', '1', 'yes', 'on'].includes(normalized)) {
    return true;
  }
  if (['false', '0', 'no', 'off'].includes(normalized)) {
    return false;
  }

  return fallback;
};
