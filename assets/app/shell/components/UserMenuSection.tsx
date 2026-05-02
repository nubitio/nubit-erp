import React, { useEffect, useMemo, useState } from 'react';
import List from 'devextreme-react/list';
import { useAuth } from '@app/contexts/auth';
import { branchService } from '@features/branch';
import type { UserMenuSectionProps } from '@assets/types';
import { UserAvatar } from '@shared/ui/library/user-avatar/UserAvatar';

import { BranchPicker } from './BranchPicker';
import { ChangePasswordModal } from './ChangePasswordModal';

import '../../../shared/ui/library/user-menu-section/UserMenuSection.scss';

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
    const subscription = branchService.getAll().subscribe({
      next: (response) => {
        const branches = response?.data ?? [];
        setCanChangeBranch(Array.isArray(branches) && branches.length > 1);
      },
      error: () => {
        setCanChangeBranch(true);
      },
    });

    return () => {
      subscription?.unsubscribe?.();
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
