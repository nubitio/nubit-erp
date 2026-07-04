import { Link } from 'react-router-dom';
import type { ReactNode } from 'react';
import { Badge } from '@nubitio/ui';

interface CpPageHeaderProps {
  title: string;
  subtitle?: string;
  breadcrumb?: { label: string; to: string };
  badge?: { label: string; variant?: 'info' | 'success' | 'warning' | 'danger' };
  actions?: ReactNode;
}

export const CpPageHeader = ({
  title,
  subtitle,
  breadcrumb,
  badge,
  actions,
}: CpPageHeaderProps) => (
  <header className="cp-page-header">
    <div className="cp-page-header__main">
      {breadcrumb && (
        <Link to={breadcrumb.to} className="cp-page-header__breadcrumb">
          <i className="ph ph-arrow-left" aria-hidden />
          {breadcrumb.label}
        </Link>
      )}
      <h1 className="cp-page-header__title">{title}</h1>
      {subtitle && <p className="cp-page-header__subtitle">{subtitle}</p>}
    </div>
    {(badge || actions) && (
      <div className="cp-page-header__aside">
        {badge && (
          <Badge variant={badge.variant ?? 'info'} pill>
            {badge.label}
          </Badge>
        )}
        {actions}
      </div>
    )}
  </header>
);