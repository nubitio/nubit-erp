import React, {useMemo, useState} from 'react';
import {useAuth} from '../../../contexts/auth';
import List from 'devextreme-react/list';

import './UserMenuSection.scss';
import type {UserMenuSectionProps} from '../../../types';
import {LocalPicker} from "../../utils/local-picker/LocalPicker";
import {prompt} from "../prompt-dialog/PromptDialog";
import {useNavigate} from "react-router-dom";

type MenuItem = {
    text: string;
    icon: string;
    onClick: () => void;
};

export const UserMenuSection = ({showAvatar, listRef}: UserMenuSectionProps) => {
    const {user, signOut} = useAuth();
    const [visible, setVisible] = useState(false);
    const [password, setPassword] = useState("");
    const navigate = useNavigate();

    const menuItems = useMemo<MenuItem[]>(
        () => [
            {
                text: 'Cambiar sucursal',
                icon: 'ph ph-building',
                onClick: () => {
                    prompt(
                        'Confirmar cambio de sucursal',
                        'Ingrese su contraseña para confirmar el cambio de sucursal',
                        'password', '')
                        .then((password) => {
                            if (password !== "") {
                                setPassword(password);
                                setVisible(true);
                            }
                        });
                }
            },
            {
                text: 'Cambiar contraseña',
                icon: 'ph ph-lock',
                onClick: () => {
                    navigate('/change-password');
                }
            },
            {
                text: 'Cerrar sesión',
                icon: 'ph ph-sign-out',
                onClick: signOut,
            },
        ],
        []
    );

    const listElementAttr = {
        class: 'user-info-list'
    };

    return (
        <>
            <div className='user-info'>
                {showAvatar && (
                    <div className='image-container'>
                        <div
                            style={{
                                backgroundImage: `url(https://robohash.org/${user?.username})`,
                            }}
                            className='user-image'
                        />
                    </div>
                )}
                <div className='user-name'>{user?.username}</div>
            </div>
            <List ref={listRef} elementAttr={listElementAttr} items={menuItems}/>
            <LocalPicker visible={visible} setVisible={setVisible} username={user?.username} password={password}/>
        </>
    );
};
