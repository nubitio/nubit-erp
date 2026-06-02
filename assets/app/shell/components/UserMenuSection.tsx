import React, { useEffect, useMemo, useState } from 'react';
import { Avatar } from '@nubit/ui';
import { useAuth } from '@app/contexts/auth';
import { branchService } from '@features/branch';
import type { UserMenuSectionProps } from '@app/types';

import './UserMenuSection.scss';

type MenuItem = {
  text: string;
  icon: string;
  closeAfterAction?: boolean;
  onClick: () => void;
};

export const UserMenuSection = ({
  showAvatar,
  onAction,
  onChangeBranch,
  onChangePassword,
}: UserMenuSectionProps) => {
  const { user, signOut } = useAuth();
  const [canChangeBranch, setCanChangeBranch] = useState(false);

  useEffect(() => {
    let cancelled = false;
    branchService
      .getAll()
      .then((response) => {
        if (cancelled) return;
        const branches = response?.data ?? [];
        setCanChangeBranch(Array.isArray(branches) && branches.length > 1);
      })
      .catch(() => {
        if (!cancelled) setCanChangeBranch(true);
      });

    return () => {
      cancelled = true;
    };
  }, []);

  const runAction = (item: MenuItem) => {
    item.onClick();
    if (item.closeAfterAction) {
      onAction?.();
    }
  };

  const menuItems = useMemo<MenuItem[]>(() => {
    const items: MenuItem[] = [];

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

    items.push(
      {
        text: 'Cambiar contraseña',
        icon: 'ph ph-lock',
        closeAfterAction: true,
        onClick: () => {
          onChangePassword?.();
        },
      },
      {
        text: 'Cerrar sesión',
        icon: 'ph ph-sign-out',
        closeAfterAction: true,
        onClick: signOut,
      },
    );

    return items;
  }, [canChangeBranch, onChangeBranch, onChangePassword, signOut]);

  return (
    <>
      <div className="user-info">
        {showAvatar && (
          <div className="image-container">
            <Avatar owner={user?.username ?? ''} size={28} />
          </div>
        )}
        <div className="user-name">{user?.username}</div>
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
