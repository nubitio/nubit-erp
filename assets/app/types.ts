import dxTreeView from 'devextreme/ui/tree_view';
import { ClickEvent } from 'devextreme/ui/button';
import { EventInfo } from 'devextreme/events';
import { ListRef } from 'devextreme-react/list';
import React from 'react';

export interface AppHeaderProps {
  menuToggleEnabled: boolean;
  title?: string;
  toggleMenu: (e: ClickEvent) => void;
  className?: string;
}

export interface SideNavigationMenuProps {
  selectedItemChanged: (e: any) => void;
  openMenu: (e: React.PointerEvent) => void;
  compactMode: boolean;
  onMenuReady: (e: EventInfo<dxTreeView>) => void;
}

export interface UserPanelProps {
  menuMode: 'context' | 'list';
}

export interface UserMenuSectionProps {
  showAvatar?: boolean;
  listRef?: React.RefObject<ListRef | null>;
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

