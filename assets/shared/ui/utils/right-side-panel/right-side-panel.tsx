import React from 'react';
import { useScreenSize } from '@utils/media-query';
import Button from 'devextreme-react/button';
import './right-side-panel.scss';

interface RightSidePanelProps {
  showOpenButton?: boolean;
  isOpened: boolean;
  toggleOpen: () => void;
  children?: React.ReactNode;
}

export const RightSidePanel = ({
  showOpenButton = true,
  isOpened,
  toggleOpen,
  children,
}: RightSidePanelProps) => {
  const { isXSmall, isLarge } = useScreenSize();
  return (
    <>
      <div
        id="right-side-panel"
        className={`right-side-panel${!isLarge ? ' overlap' : ''}${isXSmall ? ' x-small' : ''}${isOpened ? ' open' : ''}`}
      >
        {children}
      </div>
      {isLarge && showOpenButton && (
        <Button
          className={`right-panel-open-button${isOpened ? ' hidden' : ''}`}
          icon="panelright"
          onClick={toggleOpen}
        />
      )}
    </>
  );
};
