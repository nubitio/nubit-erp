import React, { ReactNode } from 'react';
import Button from 'devextreme-react/button';
import Toolbar, { Item } from 'devextreme-react/toolbar';
import './AppToolbar.scss';

type AppToolbarProps = {
  title: string;
  additionalToolbarContent?: ReactNode;
  showRefreshButton?: boolean | undefined;
  onRefresh?: () => void;
};

export const AppToolbar = (props: AppToolbarProps & React.PropsWithChildren) => {
  return (
    <div className="view-wrapper view-wrapper-dashboard">
      <Toolbar className="theme-dependent">
        <Item location="before">
          <span className="toolbar-header">{props.title}</span>
        </Item>
        {props.additionalToolbarContent}
        <Item
          location="after"
          locateInMenu="auto"
          widget="dxButton"
          showText="inMenu"
          visible={props.showRefreshButton ?? true}
        >
          <Button text="Refresh" icon="refresh" stylingMode="text" onClick={props.onRefresh} />
        </Item>
      </Toolbar>
      {props.children}
    </div>
  );
};
