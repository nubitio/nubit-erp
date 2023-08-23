import React, { ReactNode } from 'react';
import Button from 'devextreme-react/button';
import Toolbar, { Item } from 'devextreme-react/toolbar';
import './ToolbarAnalytics.scss';

type ToolbarAnalyticsProps = {
  title: string;
  additionalToolbarContent?: ReactNode;
};

export const ToolbarAnalytics = ({
  title,
  additionalToolbarContent,
  children,
}: React.PropsWithChildren<ToolbarAnalyticsProps>) => {
  return (
    <div className='view-wrapper view-wrapper-dashboard'>
      <Toolbar>
        <Item location='before'>
          <span className='toolbar-header'>{title}</span>
        </Item>
        {additionalToolbarContent}
      </Toolbar>
      {children}
    </div>
  );
};
