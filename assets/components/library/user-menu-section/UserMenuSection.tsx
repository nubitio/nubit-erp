import React, { useEffect, useMemo, useState } from 'react';
import { useAuth } from '../../../contexts/auth';
import List from 'devextreme-react/list';

import './UserMenuSection.scss';
import type { UserMenuSectionProps } from '../../../types';
import { BranchPicker } from '../../utils/branch-picker/BranchPicker';
import { useNavigate } from 'react-router-dom';
import { branchService } from '@features/branch';

type MenuItem = {
  text: string;
  icon: string;
  onClick: () => void;
};

export const UserMenuSection = ({ showAvatar, listRef }: UserMenuSectionProps) => {
  const { user, signOut } = useAuth();
  const [visible, setVisible] = useState(false);
  const [canChangeBranch, setCanChangeBranch] = useState(false);
  const navigate = useNavigate();

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

  const menuItems = useMemo<MenuItem[]>(
    () => {
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
            navigate('/change-password');
          },
        },
        {
          text: 'Cerrar sesión',
          icon: 'ph ph-sign-out',
          onClick: signOut,
        },
      );

      return items;
    },
    [canChangeBranch, navigate, signOut],
  );

  const listElementAttr = {
    class: 'user-info-list',
  };

  return (
    <>
      <div className="user-info">
        {showAvatar && (
          <div className="image-container">
            <div
              style={{
                backgroundImage: `url(https://robohash.org/${user?.username})`,
              }}
              className="user-image"
            />
          </div>
        )}
        <div className="user-name">{user?.username}</div>
      </div>
      <List ref={listRef} elementAttr={listElementAttr} items={menuItems} />
      <BranchPicker visible={visible} setVisible={setVisible} />
    </>
  );
};
