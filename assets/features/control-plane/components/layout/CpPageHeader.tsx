import { Link } from 'react-router-dom';
import type { ReactNode } from 'react';
import { PageHeader, type PageHeaderBadge } from '@nubitio/ui';

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
  <PageHeader
    title={title}
    subtitle={subtitle}
    badge={badge}
    actions={actions}
    breadcrumbSlot={
      breadcrumb ? (
        <Link to={breadcrumb.to} className="nb-page-header__breadcrumb">
          <i className="ph ph-arrow-left" aria-hidden />
          {breadcrumb.label}
        </Link>
      ) : undefined
    }
  />
);