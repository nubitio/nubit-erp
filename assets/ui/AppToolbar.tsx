import React, { ReactNode } from 'react';
import './AppToolbar.scss';

export interface AppToolbarProps {
  title: string;
  additionalToolbarContent?: ReactNode;
  showRefreshButton?: boolean;
  onRefresh?: () => void;
  icon?: string;
  refreshLabel?: string;
}

export const AppToolbar = ({
  title,
  additionalToolbarContent,
  showRefreshButton = true,
  onRefresh,
  icon,
  refreshLabel = 'Refresh',
  children,
}: AppToolbarProps & React.PropsWithChildren) => {
  return (
    <div className="view-wrapper view-wrapper-dashboard">
      <div className="app-toolbar theme-dependent">
        <div className="app-toolbar__start">
          {icon && <i className={icon} aria-hidden="true" />}
          <span className="toolbar-header">{title}</span>
        </div>
        <div className="app-toolbar__center">
          {additionalToolbarContent}
        </div>
        <div className="app-toolbar__end">
          {showRefreshButton && (
            <button
              type="button"
              className="app-icon-button"
              title={refreshLabel}
              aria-label={refreshLabel}
              onClick={onRefresh}
            >
              <i className="ph ph-arrow-clockwise" aria-hidden="true" />
            </button>
          )}
        </div>
      </div>
      {children}
    </div>
  );
};
