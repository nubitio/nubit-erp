import React, { ButtonHTMLAttributes, InputHTMLAttributes, LabelHTMLAttributes, ReactNode, SelectHTMLAttributes, TextareaHTMLAttributes } from "react";

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
//#region assets/ui/Button.d.ts
type ButtonVariant = 'primary' | 'secondary' | 'success' | 'danger' | 'ghost';
type ButtonSize = 'sm' | 'md';
interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: ButtonVariant;
  size?: ButtonSize;
  fullWidth?: boolean;
  icon?: string;
  loading?: boolean;
  children?: ReactNode;
}
declare const Button: ({
  variant,
  size,
  fullWidth,
  icon,
  loading,
  className,
  children,
  disabled,
  ...props
}: ButtonProps) => import("react/jsx-runtime").JSX.Element;
interface IconButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  icon: string;
  label: string;
  variant?: 'default' | 'danger';
}
declare const IconButton: ({
  icon,
  label,
  variant,
  className,
  ...props
}: IconButtonProps) => import("react/jsx-runtime").JSX.Element;
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
//#region assets/ui/FormControls.d.ts
interface FormFieldProps extends LabelHTMLAttributes<HTMLLabelElement> {
  label?: ReactNode;
  error?: ReactNode;
  helpText?: ReactNode;
  children: ReactNode;
}
declare const FormField: ({
  label,
  error,
  helpText,
  children,
  className,
  ...props
}: FormFieldProps) => import("react/jsx-runtime").JSX.Element;
interface TextFieldProps extends InputHTMLAttributes<HTMLInputElement> {
  invalid?: boolean;
}
declare const TextField: import("react").ForwardRefExoticComponent<TextFieldProps & import("react").RefAttributes<HTMLInputElement>>;
interface SelectFieldProps extends SelectHTMLAttributes<HTMLSelectElement> {
  invalid?: boolean;
}
declare const SelectField: import("react").ForwardRefExoticComponent<SelectFieldProps & import("react").RefAttributes<HTMLSelectElement>>;
interface TextAreaFieldProps extends TextareaHTMLAttributes<HTMLTextAreaElement> {
  invalid?: boolean;
}
declare const TextAreaField: import("react").ForwardRefExoticComponent<TextAreaFieldProps & import("react").RefAttributes<HTMLTextAreaElement>>;
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
//#region assets/ui/theme/DensityProvider.d.ts
type Density = 'normal' | 'compact';
interface DensityContextValue {
  density: Density;
  toggleDensity: () => void;
}
declare const DensityContext: React.Context<DensityContextValue | null>;
declare function useDensity(): DensityContextValue;
declare const DensityProvider: ({
  children
}: {
  children: React.ReactNode;
}) => import("react/jsx-runtime").JSX.Element;
//#endregion
//#region assets/ui/theme/useAccentColor.d.ts
interface AccentPreset {
  label: string;
  value: string;
}
declare const ACCENT_PRESETS: AccentPreset[];
declare function useAccentColor(): {
  accent: string;
  changeAccent: (color: string) => void;
  presets: AccentPreset[];
};
//#endregion
//#region assets/ui/SettingsPanel.d.ts
interface SettingsPanelProps {
  onClose?: () => void;
}
declare const SettingsPanel: ({
  onClose: _onClose
}: SettingsPanelProps) => import("react/jsx-runtime").JSX.Element;
//#endregion
//#region assets/ui/Badge.d.ts
type BadgeVariant = 'primary' | 'secondary' | 'success' | 'danger' | 'warning' | 'info' | 'light' | 'dark';
type BadgeSize = 'sm' | 'md';
interface BadgeProps {
  /** Color variant — maps to semantic CSS tokens. */
  variant?: BadgeVariant;
  /** Size: md (default) or sm. */
  size?: BadgeSize;
  /** Outlined style — transparent background with colored border and text. */
  outlined?: boolean;
  /** Render as a pill (fully rounded). */
  pill?: boolean;
  /** Dot-only mode — renders a small colored circle with no text. */
  dot?: boolean;
  children?: ReactNode;
  className?: string;
}
declare const Badge: ({
  variant,
  size,
  outlined,
  pill,
  dot,
  children,
  className
}: BadgeProps) => import("react/jsx-runtime").JSX.Element;
//#endregion
//#region assets/ui/Skeleton.d.ts
interface SkeletonProps {
  /** Rendered shape. */
  variant?: 'text' | 'rect' | 'circle';
  /** Explicit width (number = px, string = any CSS value). */
  width?: number | string;
  /** Explicit height (number = px, string = any CSS value). */
  height?: number | string;
  /** Number of text skeleton lines to render. Only used when variant="text". */
  lines?: number;
  className?: string;
}
declare const Skeleton: ({
  variant,
  width,
  height,
  lines,
  className
}: SkeletonProps) => import("react/jsx-runtime").JSX.Element;
//#endregion
//#region assets/ui/EmptyState.d.ts
type EmptyStateVariant = 'default' | 'danger' | 'warning' | 'success';
type EmptyStateSize = 'sm' | 'md' | 'lg';
interface EmptyStateProps {
  /** Phosphor icon name without the "ph-" prefix, e.g. "warning" or "folder-open". */
  icon?: string;
  /** Semantic variant — controls the icon and accent color. */
  variant?: EmptyStateVariant;
  /** Title text — required. */
  title: string;
  /** Optional supporting description. */
  description?: string;
  /** Optional action slot (buttons, links, etc.). */
  action?: ReactNode;
  /** Visual size. */
  size?: EmptyStateSize;
  /** Fill parent height — useful when used as a page-level fallback. */
  fill?: boolean;
  className?: string;
}
declare const EmptyState: ({
  icon,
  variant,
  title,
  description,
  action,
  size,
  fill,
  className
}: EmptyStateProps) => import("react/jsx-runtime").JSX.Element;
//#endregion
//#region assets/ui/Chip.d.ts
interface ChipProps {
  /** Displayed label */
  label: ReactNode;
  /** Whether the chip is in selected/active state */
  active?: boolean;
  /** Optional count badge shown inside the chip */
  count?: number;
  /** Optional leading icon class (e.g. 'ph ph-tag') */
  icon?: string;
  /** Size variant */
  size?: 'sm' | 'md' | 'lg';
  /** Disabled state */
  disabled?: boolean;
  onClick?: () => void;
  className?: string;
}
/**
 * Pill-shaped filter/tag chip. Stateless — caller controls `active`.
 * Use for category filters, tag lists, and multi-select inputs.
 */
declare const Chip: ({
  label,
  active,
  count,
  icon,
  size,
  disabled,
  onClick,
  className
}: ChipProps) => import("react/jsx-runtime").JSX.Element;
//#endregion
//#region assets/ui/Toggle.d.ts
interface ToggleProps {
  /** Controlled checked value */
  checked: boolean;
  onChange: (checked: boolean) => void;
  /** Optional label rendered in a flex row alongside the toggle */
  label?: React.ReactNode;
  /** Size variant */
  size?: 'sm' | 'md';
  disabled?: boolean;
  /** Accessible name when no visible label is provided */
  'aria-label'?: string;
  className?: string;
}
/**
 * Accessible toggle switch. Always controlled — pass `checked` + `onChange`.
 * Renders a visually-hidden `<input type="checkbox">` with a custom track.
 *
 * With `label`: wraps in `.app-toggle-row` for a label ↔ toggle layout.
 * Without `label`: renders bare `.app-toggle`.
 */
declare const Toggle: ({
  checked,
  onChange,
  label,
  size,
  disabled,
  "aria-label": ariaLabel,
  className
}: ToggleProps) => import("react/jsx-runtime").JSX.Element;
//#endregion
//#region assets/ui/ContextMenu.d.ts
interface ContextMenuItem {
  label: string;
  icon?: string;
  /** Renders item in error color */
  danger?: boolean;
  disabled?: boolean;
  onClick?: () => void;
  /** If true, renders a separator ABOVE this item */
  separator?: boolean;
}
interface ContextMenuProps {
  items: ContextMenuItem[];
  /** Whether the trigger button is visible at all */
  visible?: boolean;
  /** Custom trigger element. Defaults to three-dot icon button. */
  trigger?: ReactNode;
  /** Accessible label for the default trigger button */
  triggerLabel?: string;
  /** Alignment of the dropdown relative to the trigger */
  align?: 'right' | 'left';
  className?: string;
}
/**
 * Three-dot overflow context menu.
 * Replaces the legacy `shared/ui/library/card-menu/CardMenu` component.
 *
 * Features over CardMenu:
 * - `danger` variant items
 * - `separator` support
 * - `icon` per item
 * - Custom trigger slot
 * - Left/right alignment
 * - Click-outside + Escape to close
 */
declare const ContextMenu: ({
  items,
  visible,
  trigger,
  triggerLabel,
  align,
  className
}: ContextMenuProps) => import("react/jsx-runtime").JSX.Element | null;
//#endregion
//#region assets/ui/StatCard.d.ts
interface StatCardProps {
  title?: string;
  /** Extra content rendered in the header row (e.g. a date-range badge) */
  headerExtra?: ReactNode;
  /** Whether to show the three-dot overflow menu */
  menuVisible?: boolean;
  /** Items for the overflow menu */
  menuItems?: ContextMenuItem[];
  /** When true children are not rendered (skeleton/loading state) */
  isLoading?: boolean;
  className?: string;
  children?: ReactNode;
}
/**
 * General-purpose analytics / dashboard card.
 * Replaces the legacy `shared/ui/card-analytics/CardAnalytics` component.
 * Uses `ContextMenu` from `@nubit/ui` for the overflow menu.
 */
declare const StatCard: ({
  title,
  headerExtra,
  menuVisible,
  menuItems,
  isLoading,
  className,
  children
}: StatCardProps) => import("react/jsx-runtime").JSX.Element;
//#endregion
export { ACCENT_PRESETS, type AccentPreset, AppDialog, type AppDialogProps, AppToolbar, type AppToolbarProps, Badge, type BadgeProps, type BadgeSize, type BadgeVariant, Button, type ButtonProps, type ButtonSize, type ButtonVariant, Card, type CardProps, Chip, type ChipProps, ConfirmDialog, type ConfirmDialogProps, ContextMenu, type ContextMenuItem, type ContextMenuProps, type Density, DensityContext, type DensityContextValue, DensityProvider, EmptyState, type EmptyStateProps, FormField, type FormFieldProps, IconButton, type IconButtonProps, SelectField, type SelectFieldProps, SettingsPanel, type SettingsPanelProps, Skeleton, type SkeletonProps, StatCard, type StatCardProps, TextAreaField, type TextAreaFieldProps, TextField, type TextFieldProps, type Theme, ThemeContext, type ThemeContextValue, type ThemeMode, ThemeProvider, type ThemeProviderProps, ThemeSwitcher, type ThemeSwitcherProps, Toggle, type ToggleProps, useAccentColor, useDensity, useTheme };