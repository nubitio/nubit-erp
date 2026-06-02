import React, { useMemo } from 'react';
import { Avatar, useFloatingPanel } from '@nubit/ui';

import type { UserPanelProps } from '@app/types';
import { useAuth } from '@app/contexts/auth';
import { UserMenuSection } from './UserMenuSection';

import './UserPanel.scss';

export const UserPanel = ({ menuMode }: UserPanelProps) => {
  const { user } = useAuth();
  const username = user?.username ?? '';

  // Dropdown mode: floating panel anchored below the avatar button
  const { open, toggle, containerRef } = useFloatingPanel();

  if (menuMode === 'list') {
    return (
      <div className="user-panel">
        <UserMenuSection showAvatar />
      </div>
    );
  }

  return (
    <div className="user-panel user-panel--dropdown" ref={containerRef}>
      <button
        className="user-button"
        type="button"
        aria-label="Menú de usuario"
        aria-haspopup="menu"
        aria-expanded={open}
        onClick={toggle}
      >
        <Avatar owner={username} size={32} />
      </button>
      {open && (
        <div className="user-panel__dropdown" role="menu">
          <UserMenuSection onAction={() => toggle()} />
        </div>
      )}
    </div>
  );
};
