import React from 'react';
import Toolbar, {Item} from 'devextreme-react/toolbar';
import Button from 'devextreme-react/button';

import {Template} from 'devextreme-react/core/template';

import {UserPanel} from '../user-panel/UserPanel';
import {ThemeSwitcher} from '../theme-switcher/ThemeSwitcher';

import type {AppHeaderProps} from '../../../types';

import './AppHeader.scss';
import {Link} from "react-router-dom";

export const AppHeader = ({menuToggleEnabled, title, toggleMenu, className}: AppHeaderProps) => {
    return (
        <header className={`header-component ${className}`}>
            <Toolbar className='header-toolbar'>
                <Item visible={menuToggleEnabled} location='before' widget='dxButton' cssClass='menu-button'>
                    <Button icon='menu' stylingMode='text' onClick={toggleMenu}/>
                </Item>
                <Item location='before' cssClass='header-logo'>
                    <Link to="/dashboard">
                        <img className="header-logo" src="/logo" alt="logo"/>
                    </Link>
                </Item>
                <Item location='before' cssClass='header-title' text={title} visible={!!title}/>
                <Item location='after' locateInMenu='never'>
                    <ThemeSwitcher/>
                </Item>
                <Item location='after'>
                    <div className='messages'>
                        <Button icon='belloutline'/>
                        <div className='dx-badge'>
                            4
                        </div>
                    </div>
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
