import React, { useState } from 'react';
import Toolbar, { Item } from 'devextreme-react/toolbar';
import Button from 'devextreme-react/button';

import { Template } from 'devextreme-react/core/template';

import { UserPanel } from '../user-panel/UserPanel';
import { ThemeSwitcher } from '../theme-switcher/ThemeSwitcher';

import type { AppHeaderProps } from '../../../types';

import './AppHeader.scss';
import NotificationPanel from '../notification-panel/NotificationPanel';
import { Popover } from 'devextreme-react';
import Assistant from '../../../features/assistant/Assistant';
import { useScreenSize } from '../../../utils/media-query';

export const AppHeader = ({ menuToggleEnabled, title, toggleMenu, className }: AppHeaderProps) => {
  const [notificationPanelVisible, setNotificationPanelVisible] = useState(false);
  const [assistantVisible, setAssistantVisible] = useState(false);
  const { isXSmall } = useScreenSize();

  const toggleNotificationPanel = () => {
    setAssistantVisible(false);
    setNotificationPanelVisible((prev) => !prev);
  };

  return (
    <header className={`header-component ${className}`}>
      <Toolbar className="header-toolbar">
        <Item
          visible={menuToggleEnabled}
          location="before"
          widget="dxButton"
          cssClass="menu-button"
        >
          <Button icon="menu" stylingMode="text" onClick={toggleMenu} />
        </Item>
        <Item location="before" cssClass="header-title" text={title} visible={!!title} />
        <Item location="after" locateInMenu="never">
          <ThemeSwitcher />
        </Item>
        <Item location="after">
          <div className="messages" id="notification">
            <Button icon="belloutline" stylingMode="text" onClick={toggleNotificationPanel} />
            <div className="dx-badge"></div>
          </div>
          <Popover
            target="#notification"
            position="bottom"
            width={isXSmall ? 'calc(100vw - 20px)' : 400}
            height={380}
            visible={notificationPanelVisible}
            onHiding={() => setNotificationPanelVisible(false)}
            hideOnOutsideClick
            shading={false}
          >
            <NotificationPanel
              visible={notificationPanelVisible}
              onHiding={() => setNotificationPanelVisible(false)}
            />
          </Popover>
        </Item>
        <Item location="after">
          <div id="assistant-trigger">
            <Button
              icon="ph ph-question"
              stylingMode="text"
              hint="Assistant"
              onClick={() => {
                setNotificationPanelVisible(false);
                setAssistantVisible((prev) => !prev);
              }}
            />
          </div>
          <Popover
            target="#assistant-trigger"
            position="bottom"
            width={isXSmall ? 'calc(100vw - 20px)' : 'calc(100vw - 32px)'}
            height={isXSmall ? 'calc(100vh - 88px)' : 'calc(100vh - 120px)'}
            maxWidth={isXSmall ? undefined : 520}
            maxHeight={isXSmall ? undefined : 650}
            visible={assistantVisible}
            onHiding={() => setAssistantVisible(false)}
            shading={false}
            hideOnOutsideClick
          >
            <div className="assistant-popover">
              <Assistant showToolbar={false} />
            </div>
          </Popover>
        </Item>
        <Item location="after" locateInMenu="auto" menuItemTemplate="userPanelTemplate">
          <UserPanel menuMode="context" />
        </Item>
        <Template name="userPanelTemplate">
          <UserPanel menuMode="list" />
        </Template>
      </Toolbar>
    </header>
  );
};
