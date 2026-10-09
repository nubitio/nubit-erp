interface StatusBadgeProps {
  value: string;
  kind?: 'plan' | 'status';
}

const PLAN_CLASS: Record<string, string> = {
  free: 'ap-badge--plan-free',
  basic: 'ap-badge--plan-basic',
  pro: 'ap-badge--plan-pro',
};

const STATUS_CLASS: Record<string, string> = {
  active: 'ap-badge--status-active',
  suspended: 'ap-badge--status-danger',
  failed: 'ap-badge--status-danger',
  pending: 'ap-badge--status-pending',
  running: 'ap-badge--status-pending',
  dispatched: 'ap-badge--status-pending',
};

export function StatusBadge({ value, kind = 'status' }: StatusBadgeProps) {
  const normalized = value.toLowerCase();
  const variant =
    kind === 'plan'
      ? (PLAN_CLASS[normalized] ?? 'ap-badge--plan-free')
      : (STATUS_CLASS[normalized] ?? 'ap-badge--status-neutral');

  return <span className={`ap-badge ${variant}`}>{value}</span>;
}
