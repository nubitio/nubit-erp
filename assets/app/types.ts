export interface UserPanelProps {
  menuMode: 'context' | 'list';
}

export interface UserMenuSectionProps {
  showAvatar?: boolean;
  onAction?: () => void;
  onChangeBranch?: () => void;
  onChangePassword?: () => void;
  /** Offered only when the identity module is enabled server-side. */
  onTwoFactor?: () => void;
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

export type Handle = () => void;
