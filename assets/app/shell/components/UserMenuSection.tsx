import React, { useMemo } from 'react';
import { useSettingsDialog } from '@app/profile';
import { UserAvatar } from '@shared/components';
import { useAuth } from '@app/contexts/auth';
import type { UserMenuSectionProps } from '@app/types';
import { useTenantRuntimeConfig } from '@shared/hooks';

import './UserMenuSection.scss';

type MenuItem = {
  text: string;
  icon: string;
  closeAfterAction?: boolean;
  onClick: () => void;
};

export const UserMenuSection = ({ showAvatar, onAction, onChangeBranch }: UserMenuSectionProps) => {
  const { user, signOut } = useAuth();
  const { open: openSettings } = useSettingsDialog();
  const displayName = user?.name || user?.username || '';
  const { data: runtimeConfig, isError: runtimeConfigError } = useTenantRuntimeConfig();
  const canChangeBranch = runtimeConfigError || runtimeConfig?.ui.shell.showBranchPicker === true;

  const runAction = (item: MenuItem) => {
    item.onClick();
    if (item.closeAfterAction) {
      onAction?.();
    }
  };

  const menuItems = useMemo<MenuItem[]>(() => {
    const items: MenuItem[] = [
      {
        text: 'Ajustes',
        icon: 'ph ph-gear',
        closeAfterAction: true,
        onClick: () => openSettings(),
      },
    ];

    if (canChangeBranch) {
      items.push({
        text: 'Cambiar sucursal',
        icon: 'ph ph-building',
        closeAfterAction: true,
        onClick: () => {
          onChangeBranch?.();
        },
      });
    }

    items.push({
      text: 'Cerrar sesión',
      icon: 'ph ph-sign-out',
      closeAfterAction: true,
      onClick: signOut,
    });

    return items;
  }, [canChangeBranch, onChangeBranch, openSettings, signOut]);

  return (
    <>
      <div className="user-info">
        {showAvatar && (
          <div className="image-container">
            <UserAvatar name={displayName} avatarUrl={user?.avatarUrl} size={28} />
          </div>
        )}
        <div className="user-name">
          {displayName}
          {user?.name && user.username !== user.name && (
            <span className="user-name__username">{user.username}</span>
          )}
        </div>
      </div>
      <div className="user-info-list" role="menu" aria-label="Acciones de usuario">
        {menuItems.map((item) => (
          <button
            className="user-info-list__item"
            type="button"
            role="menuitem"
            key={item.text}
            onClick={() => runAction(item)}
          >
            <i className={item.icon} aria-hidden="true" />
            <span>{item.text}</span>
          </button>
        ))}
      </div>
    </>
  );
};
