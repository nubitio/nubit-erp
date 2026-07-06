import { Link } from 'react-router-dom';
import type { ReactNode } from 'react';

type PageHeaderBadge = {
  label: ReactNode;
  variant?: 'primary' | 'success' | 'danger' | 'warning' | 'info' | 'secondary' | 'light' | 'dark';
};

interface CpPageHeaderProps {
  title: string;
  subtitle?: string;
  breadcrumb?: { label: string; to: string };
  badge?: PageHeaderBadge;
  actions?: ReactNode;
}

export const CpPageHeader = ({
  title,
  subtitle,
  breadcrumb,
  badge,
  actions,
}: CpPageHeaderProps) => (
  <div className="nb-page-header">
    <div>
      {breadcrumb ? (
        <Link to={breadcrumb.to} className="nb-page-header__breadcrumb">
          <i className="ph ph-arrow-left" aria-hidden />
          {breadcrumb.label}
        </Link>
      ) : null}
      <h1 className="nb-page-header__title">{title}</h1>
      {subtitle ? <p className="nb-page-header__subtitle">{subtitle}</p> : null}
    </div>
    {(badge || actions) && (
      <div className="nb-page-header__aside">
        {badge ? (
          <span className={`nb-badge nb-badge--${badge.variant ?? 'secondary'}`}>{badge.label}</span>
        ) : null}
        {actions}
      </div>
    )}
  </div>
);
