import { Badge } from '@nubitio/ui';

interface TenantStatusBadgeProps {
  status?: string;
}

const labels: Record<string, string> = {
  active: 'Activo',
  suspended: 'Suspendido',
  deleted: 'Eliminado',
};

const variants: Record<string, 'success' | 'warning' | 'danger' | 'secondary'> = {
  active: 'success',
  suspended: 'warning',
  deleted: 'danger',
};

export const TenantStatusBadge = ({ status = 'active' }: TenantStatusBadgeProps) => (
  <Badge variant={variants[status] ?? 'secondary'} pill>
    {labels[status] ?? status}
  </Badge>
);