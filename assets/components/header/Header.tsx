import React, {useCallback, useRef} from 'react';
import Toolbar, {Item} from 'devextreme-react/toolbar';
import Button from 'devextreme-react/button';

import {Template} from 'devextreme-react/core/template';

import {UserPanel} from '../user-panel/UserPanel';

import type {HeaderProps} from '../../types';

import './header.scss';
import themes from "devextreme/ui/themes";
import { themeManager } from '../../core/utils/ThemeManager';

export const Header = ({menuToggleEnabled, title, toggleMenu, className}: HeaderProps) => {
    const button = useRef(null);
    const changeTheme = useCallback(() => {
        themes.ready(() => {
            console.log(themes.current());
        })
        let currentTheme = themes.current();
        themes.current(currentTheme === 'material.dark' ? 'material.light' : 'material.dark');
        themeManager.toggle();
    }, []);
    return (
        <header className={`header-component ${className}`}>
            <Toolbar className='header-toolbar'>
                <Item visible={menuToggleEnabled} location='before' widget='dxButton' cssClass='menu-button'>
                    <Button icon='menu' stylingMode='text' onClick={toggleMenu}/>
                </Item>
                <Item location='before' cssClass='header-title' text={title} visible={!!title}/>
                <Item location='after' locateInMenu='auto'>
                    <Button ref={button} className={'fas fa-sun'} onClick={changeTheme}/>
                </Item>
                <Item location='after'>
                    <div className='messages'>
                        <Button icon='bell'/>
                        <div className='dx-badge'>
                            4
                        </div>
                    </div>
                </Item>
                <Item location='after' locateInMenu='auto' menuItemTemplate='userPanelTemplate'>
                    <Button className='user-button authorization' width={150} stylingMode='text'>
                        <UserPanel menuMode='context'/>
                    </Button>
                </Item>
                <Template name='userPanelTemplate'>
                    <UserPanel menuMode='list'/>
                </Template>
            </Toolbar>
        </header>
    );
};