import React, { useEffect, useMemo, useRef, useState } from 'react';

import type { UserPanelProps } from '@app/types';
import { useAuth } from '@app/contexts/auth';
import { createUserAvatarDataUri } from './UserAvatar';

import { UserMenuSection } from './UserMenuSection';

import './UserPanel.scss';

export const UserPanel = ({ menuMode }: UserPanelProps) => {
  const { user } = useAuth();
  const [open, setOpen] = useState(false);
  const panelRef = useRef<HTMLDivElement | null>(null);
  const avatarIcon = useMemo(() => createUserAvatarDataUri(user?.username ?? ''), [user?.username]);

  useEffect(() => {
    if (!open) return;

    const handlePointerDown = (event: PointerEvent) => {
      const target = event.target as Node;
      if (target instanceof Element && target.closest('.app-dialog-root')) {
        return;
      }
      if (!panelRef.current?.contains(target)) {
        setOpen(false);
      }
    };
    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        setOpen(false);
      }
    };

    document.addEventListener('pointerdown', handlePointerDown);
    document.addEventListener('keydown', handleKeyDown);
    return () => {
      document.removeEventListener('pointerdown', handlePointerDown);
      document.removeEventListener('keydown', handleKeyDown);
    };
  }, [open]);

  if (menuMode === 'list') {
    return (
      <div className="user-panel">
        <UserMenuSection showAvatar />
      </div>
    );
  }

  return (
    <div className="user-panel user-panel--dropdown" ref={panelRef}>
      <button
        className="user-button"
        type="button"
        aria-label="Menú de usuario"
        aria-haspopup="menu"
        aria-expanded={open}
        onClick={() => setOpen((prev) => !prev)}
      >
        <img src={avatarIcon} alt="" aria-hidden="true" />
      </button>
      {open && (
        <div className="user-panel__dropdown" role="menu">
          <UserMenuSection onAction={() => setOpen(false)} />
        </div>
      )}
    </div>
  );
};
