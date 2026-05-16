import React, { useEffect, useRef, useState } from 'react';

import type { AppHeaderProps } from '@app/types';
import Assistant from '@features/assistant/Assistant';
import { useNotifications } from '@features/notifications';
import { useScreenSize } from '@utils/media-query';

import { NotificationPanel } from './NotificationPanel';
import { ThemeSwitcher } from './ThemeSwitcher';
import { UserPanel } from './UserPanel';

import './AppHeader.scss';

export const AppHeader = ({ menuToggleEnabled, title, toggleMenu, className }: AppHeaderProps) => {
  const [notificationPanelVisible, setNotificationPanelVisible] = useState(false);
  const [assistantVisible, setAssistantVisible] = useState(false);
  const notificationRef = useRef<HTMLDivElement | null>(null);
  const assistantRef = useRef<HTMLDivElement | null>(null);
  const { isXSmall } = useScreenSize();
  const { unreadCount, notifications, loading, markRead, markAllRead } = useNotifications();

  const toggleNotificationPanel = () => {
    setAssistantVisible(false);
    setNotificationPanelVisible((prev) => !prev);
  };

  useEffect(() => {
    if (!notificationPanelVisible && !assistantVisible) return;

    const handlePointerDown = (event: PointerEvent) => {
      const target = event.target as Node;
      if (notificationPanelVisible && !notificationRef.current?.contains(target)) {
        setNotificationPanelVisible(false);
      }
      if (assistantVisible && !assistantRef.current?.contains(target)) {
        setAssistantVisible(false);
      }
    };
    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        setNotificationPanelVisible(false);
        setAssistantVisible(false);
      }
    };

    document.addEventListener('pointerdown', handlePointerDown);
    document.addEventListener('keydown', handleKeyDown);
    return () => {
      document.removeEventListener('pointerdown', handlePointerDown);
      document.removeEventListener('keydown', handleKeyDown);
    };
  }, [notificationPanelVisible, assistantVisible]);

  return (
    <header className={['header-component', className].filter(Boolean).join(' ')}>
      <div className="header-toolbar" role="toolbar" aria-label="Barra principal">
        <div className="header-toolbar__before">
          {menuToggleEnabled && (
            <button
              className="app-icon-button menu-button"
              type="button"
              aria-label="Abrir menú"
              onClick={toggleMenu}
            >
              <i className="ph ph-list" aria-hidden="true" />
            </button>
          )}
          {title && <div className="header-title">{title}</div>}
        </div>
        <div className="header-toolbar__after">
          <ThemeSwitcher />
          <div className="header-popover" ref={notificationRef}>
            <div className="messages">
              <button
                className="app-icon-button"
                type="button"
                aria-label="Notificaciones"
                aria-expanded={notificationPanelVisible}
                onClick={toggleNotificationPanel}
              >
                <i className="ph ph-bell" aria-hidden="true" />
              </button>
              {unreadCount > 0 && (
                <div className="app-badge" aria-label={`${unreadCount} notificaciones sin leer`}>
                  {unreadCount > 99 ? '99+' : unreadCount}
                </div>
              )}
            </div>
            {notificationPanelVisible && (
              <div className="header-popover__panel notification-popover" role="dialog" aria-label="Notificaciones">
                <NotificationPanel
                  visible={notificationPanelVisible}
                  onHiding={() => setNotificationPanelVisible(false)}
                  notifications={notifications}
                  loading={loading}
                  markRead={markRead}
                  markAllRead={markAllRead}
                />
              </div>
            )}
          </div>
          <div className="header-popover" ref={assistantRef}>
            <button
              className="app-icon-button"
              type="button"
              aria-label="Assistant"
              aria-expanded={assistantVisible}
              onClick={() => {
                setNotificationPanelVisible(false);
                setAssistantVisible((prev) => !prev);
              }}
            >
              <i className="ph ph-sparkle" aria-hidden="true" />
            </button>
            {assistantVisible && (
              <div
                className={[
                  'header-popover__panel assistant-panel',
                  isXSmall ? 'assistant-panel--mobile' : '',
                ].filter(Boolean).join(' ')}
                role="dialog"
                aria-label="Assistant"
              >
                <div className="assistant-popover">
                  <Assistant showToolbar={false} />
                </div>
              </div>
            )}
          </div>
          <UserPanel menuMode="context" />
        </div>
      </div>
    </header>
  );
};
