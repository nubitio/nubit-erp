import React, { useCallback, useEffect, useRef, useState } from 'react';
import * as events from 'devextreme/events';
import { TreeView, TreeViewRef } from 'devextreme-react/tree-view';
import { useLocation } from 'react-router-dom';

import type { SideNavigationMenuProps } from '@assets/types';
import { useNavigation } from '@app/contexts/navigation';
import { userInformation } from '@core/common/UserInformation';
import { menuService } from '@features/navigation';
import { useScreenSize } from '@utils/media-query';

import { AppFooter } from './AppFooter';

import './SideNavigationMenu.scss';

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
  const [isLoading, setIsLoading] = useState(true);
  const { isLarge } = useScreenSize();

  const normalizePath = useCallback(
    (menuItems: SideNavigationItem[]) =>
      menuItems.map((item) => ({
        ...item,
        expanded: isLarge,
        path: item.path && !/^\//.test(item.path) ? `/${item.path}` : item.path,
        items: item.items?.map((subItem) => ({
          ...subItem,
          path: subItem.path && !/^\//.test(subItem.path) ? `/${subItem.path}` : subItem.path,
        })),
      })),
    [isLarge],
  );

  useEffect(() => {
    const roleId = userInformation().currentUserRoleId;
    let cancelled = false;
    menuService.getMenuByRoleId(roleId ?? 0).then((response) => {
        if (cancelled) return;
        const nextItems = normalizePath(response.data);
        setItems(nextItems);
      }).catch(() => {
        if (!cancelled) setIsLoading(false);
      }).finally(() => {
        if (!cancelled) setIsLoading(false);
      });
    return () => { cancelled = true; };
  }, [normalizePath]);

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- syncing drawer expansion state to breakpoint changes is intentional derived UI state
    setItems((prev) =>
      prev.map((item) => ({
        ...item,
        expanded: isLarge,
      })),
    );
  }, [isLarge]);

  const {
    navigationData: { currentPath },
  } = useNavigation();
  const location = useLocation();

  const treeViewRef = useRef<TreeViewRef>(null);
  const wrapperRef = useRef<HTMLElement | null>(null);
  const getWrapperRef = useCallback(
    (element: HTMLElement | null) => {
      const prevElement = wrapperRef.current;
      if (prevElement) {
        events.off(prevElement, 'dxclick');
      }

      wrapperRef.current = element;
      if (element) {
        events.on(element, 'dxclick', (e: React.PointerEvent) => {
          openMenu(e);
        });
      }
    },
    [openMenu],
  );

  useEffect(() => {
    const treeView = treeViewRef.current && treeViewRef.current.instance();
    if (!treeView) {
      return;
    }

    const normalizeRoute = (path?: string) => {
      if (!path) return '';
      const normalized = path.startsWith('/') ? path : `/${path}`;
      return normalized.length > 1 ? normalized.replace(/\/+$/, '') : normalized;
    };

    const flattenPaths = (menuItems: SideNavigationItem[]) => {
      const paths: string[] = [];
      menuItems.forEach((item) => {
        if (item.path) paths.push(normalizeRoute(item.path));
        item.items?.forEach((subItem) => {
          if (subItem.path) paths.push(normalizeRoute(subItem.path));
        });
      });
      return paths.filter(Boolean);
    };

    const requestedPath = normalizeRoute(currentPath || location.pathname);
    const allPaths = flattenPaths(items);
    const activePath =
      allPaths.find((path) => path === requestedPath) ??
      allPaths
        .filter((path) => requestedPath.startsWith(path) && path !== '/')
        .sort((a, b) => b.length - a.length)[0];

    if (activePath) {
      treeView.selectItem(activePath);
      treeView.expandItem(activePath);
    }

    if (compactMode) {
      treeView.collapseAll();
    }
  }, [currentPath, location.pathname, compactMode, items]);

  return (
    <div
      className={`dx-swatch-additional side-navigation-menu${compactMode ? ' compact' : ''}`}
      ref={getWrapperRef}
    >
      {children}
      <div className="menu-container theme-dependent">
        {isLoading && <div className="menu-loading">Cargando menú...</div>}
        <TreeView
          ref={treeViewRef}
          items={items}
          keyExpr="path"
          selectionMode="single"
          noDataText={isLoading ? '' : 'No hay opciones disponibles'}
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
