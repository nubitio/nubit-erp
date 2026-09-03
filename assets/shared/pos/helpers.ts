import { DateTime } from 'luxon';
import {
  formatMinorUnits,
  getCoreTimezone,
  getCoreApiBaseUrl,
  parseMoneyInput,
  toMinorUnits,
} from '@nubitio/core';

import type { PaymentMethodOption, PosItem, SunatTaxType } from './types';

export { IGV_RATE } from '@shared/lib/saleCalculations';

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

export const toMoneyString = (value: unknown, fallback = '0.00'): string => {
  const raw = String(value ?? '').trim().replace(',', '.');
  // API decimal columns commonly arrive with four fractional digits
  // (for example "5.0000"). The localized input parser intentionally caps
  // user-entered money at two decimals, so normalize canonical API decimals
  // directly before falling back to that parser.
  if (/^-?\d+(?:\.\d+)?$/.test(raw)) {
    const numeric = Number(raw);
    if (Number.isFinite(numeric)) {
      return numeric.toFixed(2);
    }
  }
  const parsed = parseMoneyInput(raw, 'PEN', 2, 'en-US');
  return parsed.ok ? formatMinorUnits(toMinorUnits(parsed.value), 2) : fallback;
};

export const isWeightUnitCode = (unitCode?: string): boolean => {
  const normalized = String(unitCode ?? '')
    .trim()
    .toUpperCase();
  return ['KGM', 'KG', 'KILO', 'KILOGRAMO', 'GRM', 'G'].includes(normalized);
};

const isKilogramUnitCode = (unitCode?: string): boolean => {
  const normalized = String(unitCode ?? '')
    .trim()
    .toUpperCase();
  return ['KGM', 'KG', 'KILO', 'KILOGRAMO'].includes(normalized);
};

export const formatPosQuantity = (value: number, unitCode?: string): string => {
  if (!isWeightUnitCode(unitCode)) {
    return Number.isInteger(value)
      ? String(value)
      : value.toFixed(3).replace(/0+$/, '').replace(/\.$/, '');
  }

  const kilogramUnit = isKilogramUnitCode(unitCode);
  if (kilogramUnit && value > 0 && value < 1) {
    return `${Math.round(value * 1000)} g`;
  }

  const quantity = value.toFixed(3).replace(/0+$/, '').replace(/\.$/, '');
  return kilogramUnit ? `${quantity} kg` : `${quantity} g`;
};

const asRecord = (value: unknown): Record<string, unknown> =>
  value !== null && typeof value === 'object' ? value as Record<string, unknown> : {};

export const normalizeProduct = (raw: unknown): PosItem => {
  const record = asRecord(raw);
  const unitOfMeasure = asRecord(record.unitOfMeasure);
  const unitOfMeasureSnake = asRecord(record.unit_of_measure);
  const categoryRecord = asRecord(record.category);
  const legacyType = record.type === undefined ? undefined : Number(record.type);
  const productType = String(
    record.productType ?? record.product_type ?? (legacyType === 1 ? 'STOCKABLE' : 'SERVICE'),
  ).toUpperCase() as PosItem['productType'];
  const productKind = String(record.productKind ?? record.product_kind ?? '').toUpperCase();
  const inventoryBehavior = String(
    record.inventoryBehavior ?? record.inventory_behavior ?? '',
  ).toUpperCase();
  const nonStockable =
    productType === 'SERVICE' ||
    productType === 'DIGITAL' ||
    productKind === 'SERVICE' ||
    productKind === 'DIGITAL' ||
    inventoryBehavior === 'NOT_TRACKED' ||
    (legacyType !== undefined && legacyType !== 1);

  const rawTaxType = record.taxType ?? record.tipAfeIgv ?? record.tip_afe_igv;
  const taxType: SunatTaxType =
    rawTaxType === '20' || rawTaxType === '30' || rawTaxType === '40'
      ? (rawTaxType as SunatTaxType)
      : '10';

  return {
    ...record,
    id: String(record.id ?? ''),
    name: String(record.name ?? 'Producto'),
    salePrice: toMoneyString(record.salePrice ?? record.precio_unitario ?? record.price),
    productType,
    trackInventory: nonStockable
      ? false
      : Boolean(
          record.trackInventory ??
          record.track_inventory ??
          record.trackInventoryFlag ??
          legacyType === 1,
        ),
    saleUnitCode: record.saleUnitCode
      ? String(record.saleUnitCode)
      : unitOfMeasure.code
        ? String(unitOfMeasure.code)
        : unitOfMeasure.name
          ? String(unitOfMeasure.name)
          : unitOfMeasureSnake.code
            ? String(unitOfMeasureSnake.code)
            : unitOfMeasureSnake.name
              ? String(unitOfMeasureSnake.name)
              : undefined,
    stock: toOptionalNumber(record.stock),
    sku: record.sku ? String(record.sku) : undefined,
    barcode: record.barcode ? String(record.barcode) : undefined,
    taxType,
    category:
      categoryRecord.id
        ? {
            id: String(categoryRecord.id),
            name: String(categoryRecord.name ?? 'Sin categoria'),
            icon: categoryRecord.icon as string | undefined,
            active: categoryRecord.active as boolean | undefined,
          }
        : {
            id: 'uncategorized',
            name: 'Sin categoria',
          },
  };
};

export const extractCollection = <T>(raw: unknown): T[] => {
  if (Array.isArray(raw)) {
    return raw as T[];
  }
  const record = asRecord(raw);
  if (Array.isArray(record['hydra:member'])) {
    return record['hydra:member'] as T[];
  }
  if (Array.isArray(record.items)) {
    return record.items as T[];
  }
  if (Array.isArray(record.data)) {
    return record.data as T[];
  }
  return [];
};

export function nowUtcIso(): string {
  return DateTime.now().toUTC().toISO() as string;
}

export function todayBusinessDate(): string {
  return DateTime.now().setZone(getCoreTimezone()).toISODate() as string;
}

export function toUtcIso(date: Date): string {
  return DateTime.fromJSDate(date, { zone: getCoreTimezone() }).toUTC().toISO() as string;
}

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

  return `${getCoreApiBaseUrl()}customers?${params.toString()}`;
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
