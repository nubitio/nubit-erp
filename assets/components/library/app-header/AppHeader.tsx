import React, {useState} from 'react';
import Toolbar, {Item} from 'devextreme-react/toolbar';
import Button from 'devextreme-react/button';

import {Template} from 'devextreme-react/core/template';

import {UserPanel} from '../user-panel/UserPanel';
import {ThemeSwitcher} from '../theme-switcher/ThemeSwitcher';

import type {AppHeaderProps} from '../../../types';

import './AppHeader.scss';
import NotificationPanel from '../notification-panel/NotificationPanel';
import {Popover} from 'devextreme-react';
import Assistant from '../../../features/assistant/Assistant';

export const AppHeader = ({menuToggleEnabled, title, toggleMenu, className}: AppHeaderProps) => {
  const [notificationPanelVisible, setNotificationPanelVisible] = useState(false);
  const [assistantVisible, setAssistantVisible] = useState(false);

  const toggleNotificationPanel = () => {
    setNotificationPanelVisible(!notificationPanelVisible);
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
          <Button icon="menu" stylingMode="text" onClick={toggleMenu}/>
        </Item>
        <Item location="before" cssClass="header-title" text={title} visible={!!title}/>
        <Item location="after" locateInMenu="never">
          <ThemeSwitcher/>
        </Item>
        <Item location="after">
          <div className="messages" id="notification">
            <Button icon="belloutline" stylingMode="text" onClick={toggleNotificationPanel}/>
            <div className="dx-badge"></div>
          </div>
          <Popover
            target="#notification"
            position="bottom"
            width={400}
            height={380}
            visible={notificationPanelVisible}
            onHiding={toggleNotificationPanel}
          >
            <NotificationPanel
              visible={notificationPanelVisible}
              onHiding={toggleNotificationPanel}
            />
          </Popover>
        </Item>
        <Item location="after">
          <div id="assistant-trigger">
            <Button
              icon="ph ph-question"
              stylingMode="text"
              hint="Assistant"
              onClick={() => setAssistantVisible((prev) => !prev)}
            />
          </div>
          <Popover
            target="#assistant-trigger"
            position="bottom"
            width="calc(100vw - 32px)"
            height="calc(100vh - 120px)"
            maxWidth={520}
            maxHeight={650}
            visible={assistantVisible}
            onHiding={() => setAssistantVisible(false)}
            shading={false}
            hideOnOutsideClick
          >
            <div className="assistant-popover">
              <Assistant showToolbar={false}/>
            </div>
          </Popover>
        </Item>
        <Item location="after" locateInMenu="auto" menuItemTemplate="userPanelTemplate">
          <UserPanel menuMode="context"/>
        </Item>
        <Template name="userPanelTemplate">
          <UserPanel menuMode="list"/>
        </Template>
      </Toolbar>
    </header>
  );
};
