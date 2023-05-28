import React, {useCallback, useEffect, useMemo, useRef, useState} from 'react';

import TreeView from 'devextreme-react/tree-view';

import {AppFooter} from '../..';
import {useNavigation} from '../../../contexts/navigation';
import {useScreenSize} from '../../../utils/media-query';

import type {SideNavigationMenuProps} from '../../../types';

import './SideNavigationMenu.scss';

import * as events from 'devextreme/events';
import {StorageUtils} from "../../../core/utils/StorageUtils";
import {menuService} from "../../../services/menu.service";

export const SideNavigationMenu = (props: React.PropsWithChildren<SideNavigationMenuProps>) => {
    const {children, selectedItemChanged, openMenu, compactMode, onMenuReady} = props;
    const [navigation, setNavigation] = useState<any[]>([]);

    const {isLarge} = useScreenSize();
    useEffect(() => {
        let session = StorageUtils.getItem('session');
        let userId = session['role_id'];
        menuService.getMenuByRoleId(userId).subscribe((response) => {
            setNavigation(response.data);
        });
    }, []);

    function normalizePath() {
        return navigation.map((item) => ({
            ...item,
            expanded: isLarge,
            path: item.path && !/^\//.test(item.path) ? `/${item.path}` : item.path
        }));
    }

    const items = useMemo(normalizePath, [navigation]);

    const {
        navigationData: {currentPath},
    } = useNavigation();

    const treeViewRef = useRef<TreeView>(null);
    const wrapperRef = useRef();
    const getWrapperRef = useCallback(
        (element) => {
            const prevElement = wrapperRef.current;
            if (prevElement) {
                events.off(prevElement, 'dxclick');
            }

            wrapperRef.current = element;
            events.on(element, 'dxclick', (e: React.PointerEvent) => {
                openMenu(e);
            });
        },
        [openMenu]
    );

    useEffect(() => {
        const treeView = treeViewRef.current && treeViewRef.current.instance;
        if (!treeView) {
            return;
        }

        if (currentPath !== undefined) {
            treeView.selectItem(currentPath);
            treeView.expandItem(currentPath);
        }

        if (compactMode) {
            treeView.collapseAll();
        }
    }, [currentPath, compactMode]);

    return (
        <div className='dx-swatch-additional side-navigation-menu' ref={getWrapperRef}>
            {children}
            <div className='menu-container'>
                <TreeView
                    ref={treeViewRef}
                    items={items}
                    keyExpr='path'
                    selectionMode='single'
                    focusStateEnabled={false}
                    expandEvent='click'
                    onItemClick={selectedItemChanged}
                    onContentReady={onMenuReady}
                    width='100%'
                />
            </div>
            <AppFooter>
                Copyright © {new Date().getFullYear()} <br/> Nubit Labs.
            </AppFooter>
        </div>
    );
};
