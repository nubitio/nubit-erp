import React, { useEffect, useMemo, useState } from 'react';
import List from 'devextreme-react/list';
import { useAuth } from '@app/contexts/auth';
import { branchService } from '@features/branch';
import type { UserMenuSectionProps } from '@app/types';
import { UserAvatar } from './UserAvatar';

import { BranchPicker } from './BranchPicker';
import { ChangePasswordModal } from './ChangePasswordModal';

import './UserMenuSection.scss';

type MenuItem = {
  text: string;
  icon: string;
  onClick: () => void;
};

export const UserMenuSection = ({ showAvatar, listRef }: UserMenuSectionProps) => {
  const { user, signOut } = useAuth();
  const [visible, setVisible] = useState(false);
  const [showChangePassword, setShowChangePassword] = useState(false);
  const [canChangeBranch, setCanChangeBranch] = useState(false);

  useEffect(() => {
    let cancelled = false;
    branchService.getAll().then((response) => {
        if (cancelled) return;
        const branches = response?.data ?? [];
        setCanChangeBranch(Array.isArray(branches) && branches.length > 1);
      }).catch(() => {
        if (!cancelled) setCanChangeBranch(true);
      });

    return () => {
      cancelled = true;
    };
  }, []);

  const menuItems = useMemo<MenuItem[]>(() => {
    const items: MenuItem[] = [];

    if (canChangeBranch) {
      items.push({
        text: 'Cambiar sucursal',
        icon: 'ph ph-building',
        onClick: () => {
          setVisible(true);
        },
      });
    }

    items.push(
      {
        text: 'Cambiar contraseña',
        icon: 'ph ph-lock',
        onClick: () => {
          setShowChangePassword(true);
        },
      },
      {
        text: 'Cerrar sesión',
        icon: 'ph ph-sign-out',
        onClick: signOut,
      },
    );

    return items;
  }, [canChangeBranch, signOut]);

  const listElementAttr = {
    class: 'user-info-list',
  };

  return (
    <>
      <div className="user-info">
        {showAvatar && (
          <div className="image-container">
            <UserAvatar owner={user?.username} size={28} />
          </div>
        )}
        <div className="user-name">{user?.username}</div>
      </div>
      <List
        ref={listRef}
        elementAttr={listElementAttr}
        items={menuItems}
        repaintChangesOnly={true}
      />
      <BranchPicker visible={visible} setVisible={setVisible} />
      <ChangePasswordModal
        visible={showChangePassword}
        onClose={() => setShowChangePassword(false)}
      />
    </>
  );
};
