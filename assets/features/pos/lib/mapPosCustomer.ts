import type { CustomerOption } from '../types';

type CustomerLike = Record<string, unknown>;

export const formatPosCustomerLabel = (item: unknown): string => {
  const row = item as CustomerLike;
  const name = String(row.businessName ?? row.name ?? '');
  const doc = row.ruc ?? row.dni ?? row.doc;
  return doc ? `${name} - ${String(doc)}` : name;
};

const extractPriceListId = (row: CustomerLike): number | undefined => {
  const raw = row.priceList ?? row.price_list;
  if (raw == null || raw === '') {
    return undefined;
  }

  if (typeof raw === 'number' && Number.isFinite(raw)) {
    return raw;
  }

  if (typeof raw === 'string') {
    const match = raw.match(/\/(\d+)\/?$/);
    if (match) {
      return Number(match[1]);
    }
    const parsed = Number(raw);
    return Number.isFinite(parsed) ? parsed : undefined;
  }

  if (typeof raw === 'object') {
    const entity = raw as CustomerLike;
    const id = entity.id ?? entity['@id'];
    if (typeof id === 'number' && Number.isFinite(id)) {
      return id;
    }
    if (typeof id === 'string') {
      const match = id.match(/\/(\d+)\/?$/);
      if (match) {
        return Number(match[1]);
      }
      const parsed = Number(id);
      return Number.isFinite(parsed) ? parsed : undefined;
    }
  }

  return undefined;
};

export const mapPosCustomer = (item: unknown): CustomerOption | null => {
  if (!item || typeof item !== 'object') {
    return null;
  }

  const row = item as CustomerLike;
  const id = Number(row.id);
  if (!Number.isFinite(id)) {
    return null;
  }

  return {
    id,
    businessName: String(row.businessName ?? row.name ?? `Cliente ${id}`),
    doc: (row.ruc ?? row.dni ?? row.doc) as string | undefined,
    priceListId: extractPriceListId(row),
  };
};