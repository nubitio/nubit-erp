import { normalizeText } from '@shared/pos/helpers';
import type { PriceEditorRow } from '../components/PriceEditorGrid';

export const matchesPriceEditorSearch = (row: PriceEditorRow, query: string): boolean => {
  const normalizedQuery = normalizeText(query);
  if (!normalizedQuery) {
    return true;
  }

  const fields = [row.name, row.sku, row.code]
    .filter(Boolean)
    .map((value) => normalizeText(String(value)));

  return fields.some((value) => value.includes(normalizedQuery));
};

export const filterPriceEditorRows = (rows: PriceEditorRow[], query: string): PriceEditorRow[] =>
  rows.filter((row) => matchesPriceEditorSearch(row, query));
