import type { ReactNode } from 'react';
import { EmptyState, Skeleton } from '@nubitio/ui';

type DashCardProps = {
  title: string;
  subtitle?: string;
  /** Header-row action, e.g. a "Ver todas" link. */
  action?: ReactNode;
  /** Show a skeleton instead of the body (first load). */
  isLoading?: boolean;
  /** Render the empty state instead of the body. */
  isEmpty?: boolean;
  emptyTitle?: string;
  emptyDescription?: string;
  emptyIcon?: string;
  /** Fixed body height — keeps chart cards aligned in a row. */
  bodyHeight?: number;
  className?: string;
  children: ReactNode;
};

/** Shared shell for every dashboard widget: header + body with loading/empty states. */
export function DashCard({
  title,
  subtitle,
  action,
  isLoading = false,
  isEmpty = false,
  emptyTitle = 'Sin datos',
  emptyDescription,
  emptyIcon = 'chart-bar',
  bodyHeight,
  className,
  children,
}: DashCardProps) {
  return (
    <section className={`efact-dash-card${className ? ` ${className}` : ''}`}>
      <header className="efact-dash-card__head">
        <div className="efact-dash-card__titles">
          <h3 className="efact-dash-card__title">{title}</h3>
          {subtitle ? <p className="efact-dash-card__subtitle">{subtitle}</p> : null}
        </div>
        {action ? <div className="efact-dash-card__action">{action}</div> : null}
      </header>
      <div
        className="efact-dash-card__body"
        style={bodyHeight ? { minHeight: bodyHeight } : undefined}
      >
        {isLoading ? (
          <Skeleton variant="rect" height={bodyHeight ?? 180} />
        ) : isEmpty ? (
          <EmptyState
            icon={emptyIcon}
            title={emptyTitle}
            description={emptyDescription}
            size="sm"
          />
        ) : (
          children
        )}
      </div>
    </section>
  );
}
