import './MasterDetailPage.scss';
import React from 'react';
import { Item, Toolbar } from 'devextreme-react/toolbar';
import Button from 'devextreme-react/button';
import { useNavigate } from 'react-router-dom';

type MasterDetailViewProps = {
  title: string;
  showBackButton?: boolean;
  leftComponent: React.ReactNode;
  rightComponent: React.ReactNode;
};

export const MasterDetailPage = (props: MasterDetailViewProps) => {
  const container = 'wrapper';
  const navigate = useNavigate();

  return (
    <div id={container} className="panels-wrapper">
      <Toolbar className="toolbar-details">
        {(props.showBackButton ?? true) && (
          <Item location="before">
            <Button
              icon="ph ph-arrow-left"
              onClick={() => {
                navigate(-1);
              }}
            />
          </Item>
        )}
        <Item location="before" text={props.title} />
      </Toolbar>
      <div className="panels">
        <div className="left">{props.leftComponent}</div>
        <div className="right">{props.rightComponent}</div>
      </div>
    </div>
  );
};
