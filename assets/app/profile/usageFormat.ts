export interface UsageLimit {
  /** -1 = the plan sets no limit. */
  limit: number;
  current: number;
}

const RESOURCES: Record<string, { label: string; unit?: string }> = {
  users: { label: 'Usuarios' },
  branches: { label: 'Sucursales' },
  customers: { label: 'Clientes' },
  products: { label: 'Productos' },
  invoices: { label: 'Comprobantes' },
  storage_mb: { label: 'Almacenamiento', unit: 'MB' },
};

const PLAN_LABELS: Record<string, string> = { free: 'Gratis', basic: 'Básico', pro: 'Pro' };

export interface UsageRow {
  key: string;
  label: string;
  /** "9 de 10 usuarios", or "9 · sin límite". */
  text: string;
  /** 0–1, or `null` when there is nothing to fill (no limit). */
  ratio: number | null;
  /** At 80% or more of the limit: worth a nudge. */
  near: boolean;
}

/** Plan name as a person would say it; an unknown plan shows its key rather than nothing. */
export const planLabel = (plan: string): string => PLAN_LABELS[plan] ?? plan;

export function describeUsage(key: string, usage: UsageLimit): UsageRow {
  const resource = RESOURCES[key] ?? { label: key };
  const unit = resource.unit ? ` ${resource.unit}` : '';
  const unlimited = usage.limit < 0;

  return {
    key,
    label: resource.label,
    text: unlimited
      ? `${usage.current}${unit} · sin límite`
      : `${usage.current}${unit} de ${usage.limit}${unit}`,
    ratio: unlimited || usage.limit === 0 ? null : Math.min(usage.current / usage.limit, 1),
    near: !unlimited && usage.limit > 0 && usage.current / usage.limit >= 0.8,
  };
}

/** Known resources first, in a stable order; anything new from the server after them. */
export function usageRows(limits: Record<string, UsageLimit>): UsageRow[] {
  const known = Object.keys(RESOURCES).filter((key) => key in limits);
  const extra = Object.keys(limits).filter((key) => !(key in RESOURCES));
  return [...known, ...extra].map((key) => describeUsage(key, limits[key] as UsageLimit));
}
