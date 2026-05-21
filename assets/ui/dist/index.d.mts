import React, { ReactNode } from "react";
//#region assets/ui/AppDialog.d.ts
interface AppDialogProps {
  visible?: boolean;
  open?: boolean;
  title: string;
  onClose: () => void;
  children: React.ReactNode;
  footer?: React.ReactNode;
  width?: number | string;
  maxWidth?: number | string;
  height?: number | string;
  fullScreen?: boolean;
  showCloseButton?: boolean;
  closeOnOutsideClick?: boolean;
  closeOnEscape?: boolean;
  restoreFocus?: boolean;
  keepMounted?: boolean;
  rootClassName?: string;
  className?: string;
  headerClassName?: string;
  titleClassName?: string;
  bodyClassName?: string;
  footerClassName?: string;
  role?: string;
}
declare const AppDialog: ({
  visible,
  open,
  title,
  onClose,
  children,
  footer,
  width,
  maxWidth,
  height,
  fullScreen,
  showCloseButton,
  closeOnOutsideClick,
  closeOnEscape,
  restoreFocus,
  keepMounted,
  rootClassName,
  className,
  headerClassName,
  titleClassName,
  bodyClassName,
  footerClassName,
  role
}: AppDialogProps) => React.ReactPortal | null;
//#endregion
//#region assets/ui/ConfirmDialog.d.ts
interface ConfirmDialogProps {
  open: boolean;
  title: string;
  message: string;
  onConfirm: () => void;
  onCancel: () => void;
  confirmLabel?: string;
  cancelLabel?: string;
  variant?: 'danger' | 'warning' | 'info';
}
declare function ConfirmDialog({
  open,
  title,
  message,
  onConfirm,
  onCancel,
  confirmLabel,
  cancelLabel,
  variant
}: ConfirmDialogProps): import("react/jsx-runtime").JSX.Element;
//#endregion
//#region assets/ui/AppToolbar.d.ts
interface AppToolbarProps {
  title: string;
  additionalToolbarContent?: ReactNode;
  showRefreshButton?: boolean;
  onRefresh?: () => void;
  icon?: string;
  refreshLabel?: string;
}
declare const AppToolbar: ({
  title,
  additionalToolbarContent,
  showRefreshButton,
  onRefresh,
  icon,
  refreshLabel,
  children
}: AppToolbarProps & React.PropsWithChildren) => import("react/jsx-runtime").JSX.Element;
//#endregion
//#region assets/ui/Card.d.ts
interface CardProps {
  title?: string;
  description?: string;
}
declare const Card: ({
  title,
  description,
  children
}: React.PropsWithChildren<CardProps>) => import("react/jsx-runtime").JSX.Element;
//#endregion
//#region assets/ui/theme/ThemeProvider.d.ts
type Theme = 'light' | 'dark';
type ThemeMode = 'light' | 'dark' | 'auto';
interface ThemeContextValue {
  theme: Theme;
  mode: ThemeMode;
  switchTheme: () => void;
  isLoaded: boolean;
}
declare const ThemeContext: React.Context<ThemeContextValue | null>;
declare function useTheme(): ThemeContextValue;
interface ThemeProviderProps {
  storageKey?: string;
  themePrefix?: string;
  basePath?: string;
  children: React.ReactNode;
}
declare const ThemeProvider: ({
  storageKey,
  themePrefix,
  basePath,
  children
}: ThemeProviderProps) => import("react/jsx-runtime").JSX.Element;
//#endregion
//#region assets/ui/theme/ThemeSwitcher.d.ts
declare const labelByMode: {
  readonly light: "Theme: Light";
  readonly dark: "Theme: Dark";
  readonly auto: "Theme: System";
};
interface ThemeSwitcherProps {
  labelByMode?: Partial<Record<keyof typeof labelByMode, string>>;
}
declare const ThemeSwitcher: ({
  labelByMode: overrides
}?: ThemeSwitcherProps) => import("react/jsx-runtime").JSX.Element;
//#endregion
export { AppDialog, type AppDialogProps, AppToolbar, type AppToolbarProps, Card, type CardProps, ConfirmDialog, type ConfirmDialogProps, type Theme, ThemeContext, type ThemeContextValue, type ThemeMode, ThemeProvider, type ThemeProviderProps, ThemeSwitcher, type ThemeSwitcherProps, useTheme };