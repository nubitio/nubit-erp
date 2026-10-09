export function stockCoveragePercent(
  qtyOnHand: string | number,
  minStock: string | number | null | undefined,
): number {
  const stock = parseFloat(String(qtyOnHand ?? '0'));
  const minimum = parseFloat(String(minStock ?? '0'));

  if (!Number.isFinite(stock) || stock <= 0) {
    return 0;
  }

  if (!Number.isFinite(minimum) || minimum <= 0) {
    return 100;
  }

  return Math.min(100, Math.max(0, Math.round((stock / minimum) * 100)));
}

export function stockCoverageColor(percent: number): string {
  if (percent <= 0) return '#ef4444';
  if (percent < 30) return '#f97316';
  if (percent < 60) return '#eab308';
  return '#22c55e';
}
