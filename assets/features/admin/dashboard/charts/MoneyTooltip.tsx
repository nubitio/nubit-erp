import { formatMoney } from '@shared/lib/displayFormat';

type TooltipEntry = {
  value?: number | string;
  name?: string;
  payload?: Record<string, unknown>;
};

type MoneyTooltipProps = {
  active?: boolean;
  label?: string | number;
  payload?: TooltipEntry[];
  /** Override the row label (e.g. show the category instead of the axis label). */
  labelKey?: string;
};

/** Themed Recharts tooltip that formats the value as PEN currency. */
export function MoneyTooltip({ active, label, payload, labelKey }: MoneyTooltipProps) {
  if (!active || !payload?.length) return null;

  const entry = payload[0];
  const value = typeof entry.value === 'number' ? entry.value : Number(entry.value ?? 0);
  const heading =
    (labelKey && (entry.payload?.[labelKey] as string | undefined)) ??
    (label != null ? String(label) : entry.name);

  return (
    <div className="dash-chart-tooltip">
      {heading ? <span className="dash-chart-tooltip__label">{heading}</span> : null}
      <span className="dash-chart-tooltip__value">{formatMoney(value)}</span>
    </div>
  );
}
