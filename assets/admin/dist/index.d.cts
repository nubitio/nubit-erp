import React from "react";

//#region assets/admin/AdminHeader.d.ts
interface AdminHeaderAction {
  id: string;
  icon: string;
  label: string;
  badge?: number;
  renderPanel: (props: {
    close: () => void;
  }) => React.ReactNode;
}
interface AdminHeaderProps {
  title?: string;
  menuToggleEnabled?: boolean;
  toggleMenu?: (e: React.MouseEvent<HTMLButtonElement>) => void;
  className?: string;
  actions?: AdminHeaderAction[];
  renderUserMenu?: (props: {
    close: () => void;
  }) => React.ReactNode;
  renderThemeSwitcher?: () => React.ReactNode;
}
declare const AdminHeader: ({
  title,
  menuToggleEnabled,
  toggleMenu,
  className,
  actions,
  renderUserMenu,
  renderThemeSwitcher
}: AdminHeaderProps) => import("react/jsx-runtime").JSX.Element;
//#endregion
//#region assets/admin/AdminSidebarMenu.d.ts
interface AdminMenuSubItem {
  text: string;
  path: string;
}
interface AdminMenuItem {
  text: string;
  path?: string;
  icon?: string;
  items?: AdminMenuSubItem[];
}
interface AdminSidebarMenuSelectEvent {
  path?: string;
  selected: boolean;
  event: React.MouseEvent<HTMLButtonElement | HTMLAnchorElement>;
}
interface AdminSidebarMenuProps {
  items: AdminMenuItem[];
  compactMode?: boolean;
  selectedItemChanged: (e: AdminSidebarMenuSelectEvent) => void;
  openMenu?: (e: React.PointerEvent) => void;
  footer?: React.ReactNode;
}
declare const AdminSidebarMenu: ({
  items,
  compactMode,
  selectedItemChanged,
  openMenu,
  footer
}: AdminSidebarMenuProps) => import("react/jsx-runtime").JSX.Element;
//#endregion
//#region assets/admin/AdminShell.d.ts
interface AdminShellProps {
  title?: string;
  menuItems: AdminMenuItem[];
  headerActions?: AdminHeaderAction[];
  renderUserMenu?: (props: {
    close: () => void;
  }) => React.ReactNode;
  renderThemeSwitcher?: () => React.ReactNode;
  footer?: React.ReactNode;
  children: React.ReactNode;
}
declare const AdminShell: ({
  title,
  menuItems,
  headerActions,
  renderUserMenu,
  renderThemeSwitcher,
  footer,
  children
}: AdminShellProps) => import("react/jsx-runtime").JSX.Element;
//#endregion
//#region assets/admin/useScreenSize.d.ts
declare const useScreenSize: () => {
  isXSmall: boolean;
  isSmall: boolean;
  isMedium: boolean;
  isLarge: boolean;
};
declare const useScreenSizeClass: () => "screen-large" | "screen-medium" | "screen-small" | "screen-x-small";
//#endregion
export { AdminHeader, type AdminHeaderAction, type AdminHeaderProps, type AdminMenuItem, type AdminMenuSubItem, AdminShell, type AdminShellProps, AdminSidebarMenu, type AdminSidebarMenuProps, type AdminSidebarMenuSelectEvent, useScreenSize, useScreenSizeClass };