interface TenantStatusBadgeProps {
  status?: string;
}

const labels: Record<string, string> = {
  active: 'Activo',
  suspended: 'Suspendido',
  deleted: 'Eliminado',
};

export const TenantStatusBadge = ({ status = 'active' }: TenantStatusBadgeProps) => (
  <span className={`cp-badge cp-badge--${status}`}>{labels[status] ?? status}</span>
);