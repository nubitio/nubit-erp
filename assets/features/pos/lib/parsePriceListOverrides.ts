import { toMoneyString } from '../posHelpers';

type ApplyRow = { id: string; price: string };

/** `GET /price-lists/{id}/apply` as a map of product id → tariff price (2 decimals). */
export const parsePriceListOverrides = (raw: unknown): Map<string, string> => {
  const record = (raw ?? {}) as Record<string, unknown>;
  const rows: ApplyRow[] = Array.isArray(raw)
    ? (raw as ApplyRow[])
    : ((record.member as ApplyRow[] | undefined) ??
      (record['hydra:member'] as ApplyRow[] | undefined) ??
      []);

  const overrides = new Map<string, string>();
  for (const row of rows) {
    const price = toMoneyString(row.price, '');
    if (price !== '') overrides.set(String(row.id), price);
  }
  return overrides;
};
