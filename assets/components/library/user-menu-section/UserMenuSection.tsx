import React, {useCallback, useMemo, useState} from 'react';
import {useAuth} from '../../../contexts/auth';
import List from 'devextreme-react/list';

import './UserMenuSection.scss';
import type {UserMenuSectionProps} from '../../../types';
import {LocalPicker} from "../local-picker/LocalPicker";

export const UserMenuSection = ({showAvatar, listRef}: UserMenuSectionProps) => {
    const {user, signOut} = useAuth();
    const [visible, setVisible] = useState(false);

    const menuItems = useMemo(
        () => [
            {
                text: 'Cambiar sucursal',
                icon: 'fas fa-store fa-1x',
                onClick: () => {
                    setVisible(true);
                }
            },
            {
                text: 'Cerrar sesión',
                icon: 'fas fa-sign-out-alt fa-1x',
                onClick: signOut,
            },
        ], []);

    const listElementAttr = {
        class: 'user-info-list'
    };

    const onItemClick = useCallback(({itemData}: any) => itemData.onClick(), []);

    return (
        <>
            <div className='user-info'>
                {showAvatar && (
                    <div className='image-container'>
                        <div
                            style={{
                                backgroundImage: `url(${user?.avatarUrl})`,
                            }}
                            className='user-image'
                        />
                    </div>
                )}
                <div className='user-name'>{user?.name}</div>
            </div>
            <List ref={listRef} elementAttr={listElementAttr} onItemClick={onItemClick} items={menuItems}/>
            <LocalPicker visible={visible} setVisible={setVisible}/>
        </>
    );
};
