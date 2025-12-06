import React, { useCallback, useEffect, useRef, useState } from 'react';

import { TreeView, TreeViewRef } from 'devextreme-react/tree-view';

import { AppFooter } from '../..';
import { useNavigation } from '../../../contexts/navigation';
import { useScreenSize } from '../../../utils/media-query';

import type { SideNavigationMenuProps } from '../../../types';
import { menuService } from '../../../service/menu.service';

import './SideNavigationMenu.scss';

import * as events from 'devextreme/events';
import { userInformation } from '../../../core/common/UserInformation';

export type SideNavigationItem = {
  expanded: boolean;
  path: string;
  text: string;
  icon: string;
  items: Array<{
    text: string;
    path: string;
  }>;
};

export const SideNavigationMenu = (props: React.PropsWithChildren<SideNavigationMenuProps>) => {
  const { children, selectedItemChanged, openMenu, compactMode, onMenuReady } = props;
  const [items, setItems] = useState<SideNavigationItem[]>([]);
  const { isLarge } = useScreenSize();

  useEffect(() => {
    let roleId = userInformation().currentUserRoleId;
    menuService.getMenuByRoleId(roleId).subscribe((response) => {
      const items = normalizePath(response.data);
      setItems(items);
    });
  }, []);

  function normalizePath(items: SideNavigationItem[]) {
    return items.map((item) => ({
      ...item,
      expanded: isLarge,
      path: item.path && !/^\//.test(item.path) ? `/${item.path}` : item.path,
      items: item.items?.map((subItem) => ({
        ...subItem,
        path: subItem.path && !/^\//.test(subItem.path) ? `/${subItem.path}` : subItem.path,
      })),
    }));
  }

  const {
    navigationData: { currentPath },
  } = useNavigation();

  const treeViewRef = useRef<TreeViewRef>(null);
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
    [openMenu],
  );

  useEffect(() => {
    const treeView = treeViewRef.current && treeViewRef.current.instance();
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
    <div className="dx-swatch-additional side-navigation-menu" ref={getWrapperRef}>
      {children}
      <div className="menu-container theme-dependent">
        <TreeView
          ref={treeViewRef}
          items={items}
          keyExpr="path"
          selectionMode="single"
          focusStateEnabled={false}
          expandEvent="click"
          onItemClick={selectedItemChanged}
          onContentReady={onMenuReady}
          width="100%"
        />
      </div>
      <AppFooter>
        Copyright © {new Date().getFullYear()} <br /> Nubit Labs.
      </AppFooter>
    </div>
  );
};
