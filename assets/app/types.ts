export interface UserPanelProps {
  menuMode: 'context' | 'list';
}

export interface UserMenuSectionProps {
  showAvatar?: boolean;
  onAction?: () => void;
  onChangeBranch?: () => void;
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
  /** Merges fresh profile data (name, photo…) into the signed-in user and the stored session. */
  updateUser: (patch: Partial<User>) => void;
  loading: boolean;
};

export type Handle = () => void;
