import React, {useState} from 'react';
import Toolbar, {Item} from 'devextreme-react/toolbar';
import Button from 'devextreme-react/button';

import {Template} from 'devextreme-react/core/template';

import {UserPanel} from '../user-panel/UserPanel';
import {ThemeSwitcher} from '../theme-switcher/ThemeSwitcher';

import type {AppHeaderProps} from '../../../types';

import './AppHeader.scss';
import NotificationPanel from "../notification-panel/NotificationPanel";
import {Popover} from "devextreme-react";
import TextBox from "devextreme-react/text-box";
import {useNavigate} from "react-router-dom";

export const AppHeader = ({menuToggleEnabled, title, toggleMenu, className}: AppHeaderProps) => {
    const [notificationPanelVisible, setNotificationPanelVisible] = useState(false);
    const navigate = useNavigate();

    const toggleNotificationPanel = () => {
        setNotificationPanelVisible(!notificationPanelVisible);
    };

    const handleSearch = (e: any) => {
        navigate(`/search/${e.component.option('value')}`);
    }

    return (
        <header className={`header-component ${className}`}>
            <Toolbar className='header-toolbar'>
                <Item visible={menuToggleEnabled} location='before' widget='dxButton' cssClass='menu-button'>
                    <Button icon='menu' stylingMode='text' onClick={toggleMenu}/>
                </Item>
                <Item location='before' cssClass='header-title' text={title} visible={!!title}/>
                <Item location='after' locateInMenu='auto' cssClass='global-search-box'>
                    <TextBox placeholder='Buscar' width={200} mode='search' stylingMode='outlined'
                             onEnterKey={handleSearch}/>
                </Item>
                <Item location='after' locateInMenu='never'>
                    <Button icon='ph ph-basket' onClick={() => navigate('/pos')}/>
                </Item>
                <Item location='after' locateInMenu='never'>
                    <ThemeSwitcher/>
                </Item>
                <Item location='after'>
                    <div className='messages' id='notification'>
                        <Button icon='belloutline' onClick={toggleNotificationPanel}/>
                        <div className='dx-badge'>
                            4
                        </div>
                    </div>
                    <Popover
                        target='#notification'
                        position='bottom'
                        width={400}
                        height={380}
                        visible={notificationPanelVisible}
                        onHiding={toggleNotificationPanel}
                    >
                        <NotificationPanel
                            visible={notificationPanelVisible}
                            onHiding={toggleNotificationPanel}
                        />
                    </Popover>
                </Item>
                <Item location='after' locateInMenu='auto' menuItemTemplate='userPanelTemplate'>
                    <UserPanel menuMode='context'/>
                </Item>
                <Template name='userPanelTemplate'>
                    <UserPanel menuMode='list'/>
                </Template>
            </Toolbar>
        </header>
    );
};
