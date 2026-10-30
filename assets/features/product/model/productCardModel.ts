import { entityLabel, formatMoney } from '@shared/lib/displayFormat';

function finiteQuantity(value: unknown): number | null {
  if ((typeof value !== 'string' && typeof value !== 'number') || String(value).trim() === '')
    return null;
  const number = Number(value);
  return Number.isFinite(number) ? number : null;
}

export function productCardModel(row: Readonly<Record<string, unknown>>) {
  const name = typeof row.name === 'string' ? row.name : 'Producto';
  const photo =
    row.photo && typeof row.photo === 'object' ? (row.photo as { path?: unknown }) : null;
  const tracksStock = row.inventoryBehavior !== 'NOT_TRACKED';
  // Product payloads do not always publish a warehouse-scoped quantity. Never
  // interpret an absent stock value as zero or sum different warehouses here.
  const stock = finiteQuantity(row.stock);
  const minimum = finiteQuantity(row.minStock);
  const stockLabel = !tracksStock
    ? 'Sin control de stock'
    : stock === null
      ? 'Consultar existencias'
      : stock <= 0
        ? 'Agotado'
        : minimum !== null && stock < minimum
          ? `Stock bajo · ${stock}`
          : `Stock · ${stock}`;
  const tone =
    !tracksStock || stock === null
      ? 'secondary'
      : stock <= 0
        ? 'danger'
        : minimum !== null && stock < minimum
          ? 'warning'
          : 'success';
  return {
    name,
    image: typeof photo?.path === 'string' && photo.path.trim() ? photo.path : null,
    category: entityLabel(row.category) || 'Categoría no disponible',
    code:
      typeof row.sku === 'string' && row.sku.trim()
        ? row.sku
        : typeof row.code === 'string' && row.code.trim()
          ? row.code
          : 'Sin SKU',
    price: formatMoney(row.salePrice, String(row.currency ?? 'PEN')),
    stockLabel,
    tone: tone as 'secondary' | 'danger' | 'warning' | 'success',
  };
}
