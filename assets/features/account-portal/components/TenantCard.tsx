import { Link } from 'react-router-dom';
import { Button } from '@nubitio/ui';
import type { TenantRecord } from '../api/accountPortalClient';
import { StatusBadge } from './StatusBadge';

interface TenantCardProps {
  tenant: TenantRecord;
}

export function TenantCard({ tenant }: TenantCardProps) {
  return (
    <article className="ap-tenant-card">
      <div className="ap-tenant-card__head">
        <div>
          <h3 className="ap-tenant-card__name">{tenant.name}</h3>
          {tenant.primary_domain ? (
            <code className="ap-tenant-card__domain">{tenant.primary_domain}</code>
          ) : (
            <span className="ap-tenant-card__domain ap-tenant-card__domain--empty">Sin dominio</span>
          )}
        </div>
        <div className="ap-tenant-card__badges">
          <StatusBadge value={tenant.plan ?? 'free'} kind="plan" />
          <StatusBadge value={tenant.status} kind="status" />
        </div>
      </div>

      <div className="ap-tenant-card__actions">
        <Link to={`/account/tenant/${tenant.id}`}>
          <Button type="button" variant="secondary" size="sm">
            Gestionar
          </Button>
        </Link>
        {tenant.primary_domain ? (
          <a href={`https://${tenant.primary_domain}`} target="_blank" rel="noopener noreferrer">
            <Button type="button" variant="primary" size="sm">
              Abrir
              <i className="ph ph-arrow-square-out" aria-hidden="true" />
            </Button>
          </a>
        ) : null}
      </div>
    </article>
  );
}