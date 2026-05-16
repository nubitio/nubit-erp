import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { useLocation } from 'react-router-dom';
import type { SideNavigationMenuProps } from '@app/types';
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

const normalizeRoute = (path?: string) => {
  if (!path) return '';
  const normalized = path.startsWith('/') ? path : `/${path}`;
  return normalized.length > 1 ? normalized.replace(/\/+$/, '') : normalized;
};

const getIconClassName = (icon?: string) => {
  if (!icon) return '';
  return icon.includes(' ') ? icon : `ph ph-${icon}`;
};

export const SideNavigationMenu = (props: React.PropsWithChildren<SideNavigationMenuProps>) => {
  const { children, selectedItemChanged, openMenu, compactMode } = props;
  const [items, setItems] = useState<SideNavigationItem[]>([]);
  const [expandedItemKeys, setExpandedItemKeys] = useState<Set<string>>(new Set());
  const [isLoading, setIsLoading] = useState(true);
  const { isLarge } = useScreenSize();
  const location = useLocation();

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
    menuService
      .getMenuByRoleId(roleId ?? 0)
      .then((response) => {
        if (cancelled) return;
        const nextItems = normalizePath(response.data);
        setItems(nextItems);
        setExpandedItemKeys(new Set(isLarge ? nextItems.map((item) => item.path || item.text) : []));
      })
      .catch(() => {
        if (!cancelled) setIsLoading(false);
      })
      .finally(() => {
        if (!cancelled) setIsLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [isLarge, normalizePath]);

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- syncing menu expansion state to breakpoint changes is intentional derived UI state
    setExpandedItemKeys(new Set(isLarge ? items.map((item) => item.path || item.text) : []));
  }, [isLarge, items]);

  const activePath = useMemo(() => {
    const flattenPaths = (menuItems: SideNavigationItem[]) =>
      menuItems
        .flatMap((item) => [
          normalizeRoute(item.path),
          ...(item.items?.map((subItem) => normalizeRoute(subItem.path)) ?? []),
        ])
        .filter(Boolean);

    const requestedPath = normalizeRoute(location.pathname);
    const allPaths = flattenPaths(items);
    return (
      allPaths.find((path) => path === requestedPath) ??
      allPaths
        .filter((path) => requestedPath.startsWith(path) && path !== '/')
        .sort((a, b) => b.length - a.length)[0]
    );
  }, [location.pathname, items]);

  const isSelected = useCallback((path?: string) => normalizeRoute(path) === activePath, [activePath]);

  const parentContainsSelected = useCallback(
    (item: SideNavigationItem) => item.items?.some((subItem) => isSelected(subItem.path)) ?? false,
    [isSelected],
  );

  useEffect(() => {
    const activeParent = items.find((item) => parentContainsSelected(item));
    if (!activeParent || compactMode) return;

    const activeParentKey = activeParent.path || activeParent.text;
    // eslint-disable-next-line react-hooks/set-state-in-effect -- route changes should reveal the active child menu item
    setExpandedItemKeys((prev) => {
      if (prev.has(activeParentKey)) return prev;
      const next = new Set(prev);
      next.add(activeParentKey);
      return next;
    });
  }, [compactMode, items, parentContainsSelected]);

  const handleSelect = useCallback(
    (path: string | undefined, selected: boolean, event: React.MouseEvent<HTMLButtonElement>) => {
      selectedItemChanged({ path, selected, event });
    },
    [selectedItemChanged],
  );

  const toggleExpanded = useCallback((key: string) => {
    setExpandedItemKeys((prev) => {
      const next = new Set(prev);
      if (next.has(key)) {
        next.delete(key);
      } else {
        next.add(key);
      }
      return next;
    });
  }, []);

  return (
    <div
      className={`side-navigation-menu${compactMode ? ' compact' : ''}`}
      onPointerDown={openMenu}
    >
      {children}
      <div className="menu-container theme-dependent">
        {isLoading && <div className="menu-loading">Cargando menú...</div>}
        {!isLoading && items.length === 0 && <div className="menu-empty">No hay opciones disponibles</div>}
        <nav className="side-navigation-menu__nav" aria-label="Navegación principal">
          {items.map((item) => {
            const selected = isSelected(item.path);
            const containsSelected = parentContainsSelected(item);
            const itemKey = item.path || item.text;
            const hasChildren = item.items?.length > 0;
            const expanded = !compactMode && hasChildren && expandedItemKeys.has(itemKey);

            return (
              <section
                className={`side-navigation-menu__section${containsSelected ? ' has-selected-child' : ''}`}
                key={itemKey}
              >
                <button
                  className={`side-navigation-menu__item side-navigation-menu__item--parent${selected ? ' is-selected' : ''}`}
                  type="button"
                  aria-current={selected ? 'page' : undefined}
                  aria-expanded={hasChildren ? expanded : undefined}
                  onClick={(event) => {
                    if (hasChildren) {
                      toggleExpanded(itemKey);
                      return;
                    }
                    handleSelect(item.path, selected, event);
                  }}
                >
                  <span className={`side-navigation-menu__icon ${getIconClassName(item.icon)}`} aria-hidden="true" />
                  <span className="side-navigation-menu__text">{item.text}</span>
                  {hasChildren && (
                    <span
                      className={`side-navigation-menu__chevron${expanded ? ' is-expanded' : ''}`}
                      aria-hidden="true"
                    />
                  )}
                </button>
                {expanded && item.items?.length > 0 && (
                  <div className="side-navigation-menu__children">
                    {item.items.map((subItem) => {
                      const childSelected = isSelected(subItem.path);

                      return (
                        <button
                          className={`side-navigation-menu__item side-navigation-menu__item--child${childSelected ? ' is-selected' : ''}`}
                          type="button"
                          key={subItem.path || subItem.text}
                          aria-current={childSelected ? 'page' : undefined}
                          onClick={(event) => handleSelect(subItem.path, childSelected, event)}
                        >
                          <span className="side-navigation-menu__text">{subItem.text}</span>
                        </button>
                      );
                    })}
                  </div>
                )}
              </section>
            );
          })}
        </nav>
      </div>
      <AppFooter>
        Copyright © {new Date().getFullYear()} <br /> Nubit Labs.
      </AppFooter>
    </div>
  );
};
