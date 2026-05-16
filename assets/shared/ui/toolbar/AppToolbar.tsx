import React, { ReactNode } from 'react';
import './AppToolbar.scss';

type AppToolbarProps = {
  title: string;
  additionalToolbarContent?: ReactNode;
  showRefreshButton?: boolean | undefined;
  onRefresh?: () => void;
  icon?: string;
};

export const AppToolbar = (props: AppToolbarProps & React.PropsWithChildren) => {
  return (
    <div className="view-wrapper view-wrapper-dashboard">
      <div className="app-toolbar theme-dependent">
        <div className="app-toolbar__start">
          {props.icon && <i className={props.icon} aria-hidden="true" />}
          <span className="toolbar-header">{props.title}</span>
        </div>
        <div className="app-toolbar__center">
          {props.additionalToolbarContent}
        </div>
        <div className="app-toolbar__end">
          {(props.showRefreshButton ?? true) && (
            <button
              type="button"
              className="app-icon-button"
              title="Actualizar"
              aria-label="Actualizar"
              onClick={props.onRefresh}
            >
              <i className="ph ph-arrow-clockwise" aria-hidden="true" />
            </button>
          )}
        </div>
      </div>
      {props.children}
    </div>
  );
};
