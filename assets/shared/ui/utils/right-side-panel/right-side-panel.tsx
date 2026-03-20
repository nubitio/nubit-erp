import React from 'react';
import classNames from 'classnames';
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
        className={classNames({
          'right-side-panel': true,
          overlap: !isLarge,
          'x-small': isXSmall,
          open: isOpened,
        })}
      >
        {children}
      </div>
      {isLarge && showOpenButton && (
        <Button
          className={classNames({
            'right-panel-open-button': true,
            hidden: isOpened,
          })}
          icon="panelright"
          onClick={toggleOpen}
        />
      )}
    </>
  );
};
