/** A line of an offline sale accepted at a price other than the one in force (GET /api/sale-price-review). */
export interface PriceReviewLine {
  lineId: number;
  saleId: string;
  document: string;
  issuedAt: string | null;
  seller: string | null;
  product: string;
  quantity: string;
  chargedPrice: string;
  expectedPrice: string;
  difference: string;
  differencePercent: string | null;
  /** Gained (+) or given away (-) on the line against the current price. */
  impact: string;
}

/** What the business gained (+) or gave away (-) across all lines, in cents to avoid float drift. */
export const totalImpact = (lines: PriceReviewLine[]): number =>
  lines.reduce((sum, line) => sum + Math.round(Number(line.impact) * 100), 0) / 100;

export const formatPercent = (value: string | null): string => {
  if (value === null) return '—';
  const number = Number(value);
  if (!Number.isFinite(number)) return '—';
  const sign = number > 0 ? '+' : '';
  return `${sign}${number.toFixed(1)} %`;
};

/** Sale lines grouped by seller, to see whether the differences cluster on one till or person. */
export const countBySeller = (
  lines: PriceReviewLine[],
): Array<{ seller: string; count: number }> => {
  const counts = new Map<string, number>();
  for (const line of lines) {
    const seller = line.seller ?? 'Sin vendedor';
    counts.set(seller, (counts.get(seller) ?? 0) + 1);
  }
  return [...counts.entries()]
    .map(([seller, count]) => ({ seller, count }))
    .sort((a, b) => b.count - a.count || a.seller.localeCompare(b.seller));
};
