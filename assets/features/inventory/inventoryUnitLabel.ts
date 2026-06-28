type UnitSource = {
  unitName?: string | null;
  unitCode?: string | null;
  product?: unknown;
};

const KILO_CODES = new Set(['KGM', 'KG', 'KILO', 'KILOGRAMO']);
const GRAM_CODES = new Set(['GRM', 'G']);

function asRecord(value: unknown): Record<string, unknown> {
  return value !== null && typeof value === 'object' ? (value as Record<string, unknown>) : {};
}

function readNestedUnit(product: unknown): { name?: string; code?: string } {
  const record = asRecord(product);
  const unit = asRecord(record.unitOfMeasure ?? record.unit_of_measure);
  const name = typeof unit.name === 'string' ? unit.name.trim() : '';
  const code = typeof unit.code === 'string' ? unit.code.trim() : '';
  return { name: name || undefined, code: code || undefined };
}

function friendlyUnitLabel(name?: string, code?: string): string | null {
  const normalizedCode = (code ?? name ?? '').trim().toUpperCase();
  if (KILO_CODES.has(normalizedCode)) return 'kilos';
  if (GRAM_CODES.has(normalizedCode)) return 'g';
  if (name) return name;
  return null;
}

export function resolveStockUnitLabel(source: UnitSource): string {
  const direct = friendlyUnitLabel(
    source.unitName?.trim() || undefined,
    source.unitCode?.trim() || undefined,
  );
  if (direct) return direct;

  const nested = readNestedUnit(source.product);
  const nestedLabel = friendlyUnitLabel(nested.name, nested.code);
  if (nestedLabel) return nestedLabel;

  return 'unidades';
}