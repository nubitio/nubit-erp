import React, { useState, useCallback, useEffect } from 'react';

import { useNavigate } from 'react-router';

import { AppFooter, AppHeader, SideNavigationMenu } from '@app/shell/components';
import { useScreenSize } from '@utils/media-query';

import type { SideNavigationSelectEvent, SideNavToolbarProps } from '@app/types';

import './side-nav-outer-toolbar.scss';

enum MenuOpenState {
  Closed = 1,
  Opened = 2,
  TemporaryOpened = 3,
}

type MenuStatus = MenuOpenState | null;

export const SideNavOuterToolbar = ({
  title,
  children,
}: React.PropsWithChildren<SideNavToolbarProps>) => {
  const navigate = useNavigate();
  const { isXSmall, isLarge } = useScreenSize();
  const [menuStatus, setMenuStatus] = useState<MenuStatus>(null);

  const getDefaultMenuOpenState = useCallback(
    () => (isLarge ? MenuOpenState.Opened : MenuOpenState.Closed),
    [isLarge],
  );
  const getMenuOpenState = useCallback(
    (status: MenuStatus) => {
      if (status === null) {
        return getDefaultMenuOpenState();
      }

      return status;
    },
    [getDefaultMenuOpenState],
  );

  const getMenuStatus = useCallback(
    (status: MenuStatus) => {
      return status === getDefaultMenuOpenState() ? null : status;
    },
    [getDefaultMenuOpenState],
  );

  const changeMenuStatus = useCallback(
    (reducerFn: (prevStatus: MenuStatus) => MenuStatus) => {
      setMenuStatus((prevMenuStatus) =>
        getMenuStatus(reducerFn(getMenuOpenState(prevMenuStatus)) ?? prevMenuStatus),
      );
    },
    [getMenuOpenState, getMenuStatus],
  );

  const toggleMenu = useCallback(
    (event: React.MouseEvent<HTMLButtonElement>) => {
      changeMenuStatus((prevStatus) =>
        prevStatus === MenuOpenState.Closed ? MenuOpenState.Opened : MenuOpenState.Closed,
      );
      event.stopPropagation();
    },
    [changeMenuStatus],
  );

  const temporaryOpenMenu = useCallback(() => {
    changeMenuStatus((prevStatus) =>
      prevStatus === MenuOpenState.Closed ? MenuOpenState.TemporaryOpened : null,
    );
  }, [changeMenuStatus]);

  const closeMenuFromOverlay = useCallback(() => {
    changeMenuStatus((prevStatus) =>
      prevStatus !== MenuOpenState.Closed && !isLarge ? MenuOpenState.Closed : null,
    );
  }, [isLarge, changeMenuStatus]);

  const onNavigationChanged = useCallback(
    ({ path, event, selected }: SideNavigationSelectEvent) => {
      if (getMenuOpenState(menuStatus) === MenuOpenState.Closed || !path || selected) {
        event?.preventDefault();
        return;
      }

      navigate(path);
      if (!isLarge || menuStatus === MenuOpenState.TemporaryOpened) {
        setMenuStatus(getMenuStatus(MenuOpenState.Closed));
        event?.stopPropagation();
      }
    },
    [navigate, menuStatus, isLarge, getMenuOpenState, getMenuStatus],
  );

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- syncing menu status to screen size change; intentional derived state update
    changeMenuStatus(() => menuStatus);
  }, [isLarge, changeMenuStatus, menuStatus]);

  const menuOpenState = getMenuOpenState(menuStatus);
  const isMenuOpen = menuOpenState !== MenuOpenState.Closed;
  const isCompact = menuOpenState === MenuOpenState.Closed;
  const bodyClassName = [
    'side-nav-outer-toolbar__body layout-body',
    isMenuOpen ? 'is-open' : 'is-closed',
    isCompact ? 'is-compact' : '',
    isLarge ? 'is-large' : 'is-overlay',
    isXSmall ? 'is-xsmall' : '',
  ]
    .filter(Boolean)
    .join(' ');

  return (
    <div className="side-nav-outer-toolbar">
      <AppHeader
        className="layout-header"
        menuToggleEnabled
        toggleMenu={toggleMenu}
        title={title}
      />
      <div className={bodyClassName}>
        <aside className="side-nav-outer-toolbar__panel" aria-label="Menú principal">
          <SideNavigationMenu
            compactMode={isCompact}
            selectedItemChanged={onNavigationChanged}
            openMenu={temporaryOpenMenu}
          />
        </aside>
        {!isLarge && isMenuOpen && (
          <button
            className="side-nav-outer-toolbar__scrim"
            type="button"
            aria-label="Cerrar menú"
            onClick={closeMenuFromOverlay}
          />
        )}
        <main className="side-nav-outer-toolbar__content content">
          {React.Children.map(children, (item) => {
            return React.isValidElement(item) && item.type !== AppFooter && item;
          })}
        </main>
      </div>
    </div>
  );
};
