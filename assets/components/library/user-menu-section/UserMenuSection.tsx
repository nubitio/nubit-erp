import React, {useMemo, useState} from 'react';
import {useAuth} from '../../../contexts/auth';
import List from 'devextreme-react/list';

import './UserMenuSection.scss';
import type {UserMenuSectionProps} from '../../../types';
import {BranchPicker} from '../../utils/branch-picker/BranchPicker';
import {useNavigate} from 'react-router-dom';

type MenuItem = {
  text: string;
  icon: string;
  onClick: () => void;
};

export const UserMenuSection = ({showAvatar, listRef}: UserMenuSectionProps) => {
  const {user, signOut} = useAuth();
  const [visible, setVisible] = useState(false);
  const navigate = useNavigate();

  const menuItems = useMemo<MenuItem[]>(
    () => [
      {
        text: 'Cambiar sucursal',
        icon: 'ph ph-building',
        onClick: () => {
          setVisible(true);
        },
      },
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
    ],
    [],
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
      <List ref={listRef} elementAttr={listElementAttr} items={menuItems}/>
      <BranchPicker
        visible={visible}
        setVisible={setVisible}
      />
    </>
  );
};
