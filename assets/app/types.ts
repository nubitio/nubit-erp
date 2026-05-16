import React from 'react';

export interface AppHeaderProps {
  menuToggleEnabled: boolean;
  title?: string;
  toggleMenu: (e: React.MouseEvent<HTMLButtonElement>) => void;
  className?: string;
}

export interface SideNavigationMenuProps {
  selectedItemChanged: (e: SideNavigationSelectEvent) => void;
  openMenu: (e: React.PointerEvent) => void;
  compactMode: boolean;
}

export interface SideNavigationSelectEvent {
  path?: string;
  selected: boolean;
  event: React.MouseEvent<HTMLButtonElement | HTMLAnchorElement>;
}

export interface UserPanelProps {
  menuMode: 'context' | 'list';
}

export interface UserMenuSectionProps {
  showAvatar?: boolean;
  onAction?: () => void;
}

export interface User {
  userId: number;
  username: string;
  email: string;
  name: string;
  role: number;
  roles: string[];
  warehouseId: number;
  avatarUrl: string;
}

export type AuthContextType = {
  user?: User;
  signIn: (
    email: string,
    password: string,
  ) => Promise<{ isOk: boolean; data?: User; message?: string }>;
  signOut: () => void;
  loading: boolean;
};

export interface SideNavToolbarProps {
  title: string;
}

export interface SingleCardProps {
  title?: string;
  description?: string;
}

export type Handle = () => void;
