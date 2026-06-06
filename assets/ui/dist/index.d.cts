import React$1, { ButtonHTMLAttributes, InputHTMLAttributes, LabelHTMLAttributes, ReactNode, SelectHTMLAttributes, TextareaHTMLAttributes } from "react";

//#region assets/ui/Avatar.d.ts
type AvatarShape = 'circle' | 'square';
type AvatarSize = 'xs' | 'sm' | 'md' | 'lg' | 'xl';
interface AvatarProps {
  /**
   * The name used to derive initials and background color.
   * Initials are extracted from up to the first two words.
   */
  owner?: string;
  /**
   * Explicit size in px. If not provided, uses one of the named sizes.
   * Named sizes: xs=20, sm=24, md=32, lg=40, xl=48.
   */
  size?: number;
  /** Named size variant — overridden by explicit `size` prop. */
  variant?: AvatarSize;
  /** Circle (default) or rounded-square. */
  shape?: AvatarShape;
  /** Optional alt text. Defaults to owner name or "Avatar". */
  alt?: string;
  className?: string;
}
declare function getAvatarInitials(owner?: string): string;
declare function getAvatarHue(owner?: string): number;
declare const Avatar: ({
  owner,
  size,
  variant,
  shape,
  alt,
  className
}: AvatarProps) => React$1.JSX.Element;
//#endregion
//#region assets/ui/AppDialog.d.ts
interface AppDialogProps {
  visible?: boolean;
  open?: boolean;
  title: string;
  onClose: () => void;
  children: React$1.ReactNode;
  footer?: React$1.ReactNode;
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
}: AppDialogProps) => React$1.ReactPortal | null;
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
}: ConfirmDialogProps): import("react").JSX.Element;
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
}: AppToolbarProps & React$1.PropsWithChildren) => React$1.JSX.Element;
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
}: ButtonProps) => import("react").JSX.Element;
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
}: IconButtonProps) => import("react").JSX.Element;
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
}: React$1.PropsWithChildren<CardProps>) => React$1.JSX.Element;
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
}: FormFieldProps) => import("react").JSX.Element;
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
//#region assets/ui/AppDropdown.d.ts
interface AppDropdownOption {
  value: string;
  label: string;
  disabled?: boolean;
}
type AppDropdownVariant = 'form' | 'toolbar';
interface AppDropdownProps {
  id?: string;
  label?: ReactNode;
  icon?: string;
  value: string;
  options: AppDropdownOption[];
  onChange: (value: string) => void;
  disabled?: boolean;
  placeholder?: string;
  variant?: AppDropdownVariant;
  className?: string;
  /** When true, wraps the control in a form-field label (form variant only). */
  showFieldLabel?: boolean;
  error?: ReactNode;
  helpText?: ReactNode;
}
declare function AppDropdown({
  id: idProp,
  label,
  icon,
  value,
  options,
  onChange,
  disabled,
  placeholder,
  variant,
  className,
  showFieldLabel,
  error,
  helpText
}: AppDropdownProps): import("react").JSX.Element;
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
declare const ThemeContext: React$1.Context<ThemeContextValue | null>;
declare function useTheme(): ThemeContextValue;
interface ThemeProviderProps {
  storageKey?: string;
  themePrefix?: string;
  basePath?: string;
  children: React$1.ReactNode;
}
declare const ThemeProvider: ({
  storageKey,
  themePrefix,
  basePath,
  children
}: ThemeProviderProps) => React$1.JSX.Element;
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
}?: ThemeSwitcherProps) => React$1.JSX.Element;
//#endregion
//#region assets/ui/theme/DensityProvider.d.ts
type Density = 'normal' | 'compact';
interface DensityContextValue {
  density: Density;
  toggleDensity: () => void;
}
declare const DensityContext: React$1.Context<DensityContextValue | null>;
declare function useDensity(): DensityContextValue;
declare const DensityProvider: ({
  children
}: {
  children: React$1.ReactNode;
}) => React$1.JSX.Element;
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
}: SettingsPanelProps) => React$1.JSX.Element;
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
}: BadgeProps) => import("react").JSX.Element;
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
}: SkeletonProps) => import("react").JSX.Element;
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
}: EmptyStateProps) => import("react").JSX.Element;
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
}: ChipProps) => React$1.JSX.Element;
//#endregion
//#region assets/ui/Toggle.d.ts
interface ToggleProps {
  /** Controlled checked value */
  checked: boolean;
  onChange: (checked: boolean) => void;
  /** Optional label rendered in a flex row alongside the toggle */
  label?: React$1.ReactNode;
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
}: ToggleProps) => React$1.JSX.Element;
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
}: ContextMenuProps) => React$1.JSX.Element | null;
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
}: StatCardProps) => React$1.JSX.Element;
//#endregion
//#region assets/ui/CollapsibleSection.d.ts
interface CollapsibleSectionProps {
  /** Section heading text. */
  label: React$1.ReactNode;
  /** Optional Phosphor icon class string, e.g. "ph ph-chat-circle". */
  icon?: string;
  /** Controlled open state. */
  open: boolean;
  /** Called when the header trigger is clicked. */
  onToggle: () => void;
  /** Body content — always mounted, hidden via CSS when collapsed. */
  children: React$1.ReactNode;
  /**
   * Optional trailing slot rendered to the right of the label.
   * Useful for a clear button or indicator badge.
   */
  trailing?: React$1.ReactNode;
  className?: string;
  /** Additional class applied to the body container. */
  bodyClassName?: string;
  /** Forwarded to the root element for testing. */
  'data-testid'?: string;
}
/**
 * Accessible collapsible section with a labelled header trigger.
 *
 * - Uses `aria-expanded` and `aria-controls` for screen reader support.
 * - The body is conditionally rendered (not just hidden) to avoid mounting
 *   heavy children before they are needed. Pass `keepMounted` if you need
 *   the body always in the DOM.
 */
declare const CollapsibleSection: ({
  label,
  icon,
  open,
  onToggle,
  children,
  trailing,
  className,
  bodyClassName,
  "data-testid": testId
}: CollapsibleSectionProps) => React$1.JSX.Element;
//#endregion
//#region assets/ui/DatePicker.d.ts
interface DatePickerProps {
  id?: string;
  name?: string;
  value?: string | null;
  placeholder?: string;
  disabled?: boolean;
  readOnly?: boolean;
  required?: boolean;
  invalid?: boolean;
  locale?: string;
  /** Show the inline ✕ clear button when a value is present. Default: true.
   *  Set to false when the parent (e.g. datagrid filter row) already provides its own clear action. */
  clearable?: boolean;
  min?: string;
  max?: string;
  className?: string;
  ariaLabel?: string;
  onChange?: (value: string) => void;
  onBlur?: React$1.FocusEventHandler<HTMLElement>;
}
declare const DatePicker: React$1.ForwardRefExoticComponent<DatePickerProps & React$1.RefAttributes<HTMLInputElement>>;
//#endregion
//#region assets/ui/DateRangePicker.d.ts
interface DateRangePickerProps {
  startValue?: string | null;
  endValue?: string | null;
  placeholder?: string;
  disabled?: boolean;
  readOnly?: boolean;
  required?: boolean;
  invalid?: boolean;
  locale?: string;
  min?: string;
  max?: string;
  className?: string;
  ariaLabel?: string;
  onChange?: (startValue: string, endValue: string) => void;
  onBlur?: React$1.FocusEventHandler<HTMLElement>;
}
declare function DateRangePicker({
  startValue,
  endValue,
  placeholder,
  disabled,
  readOnly,
  required,
  invalid,
  locale,
  min,
  max,
  className,
  ariaLabel,
  onChange,
  onBlur
}: DateRangePickerProps): React$1.JSX.Element;
//#endregion
//#region assets/ui/Popover.d.ts
type PopoverAlign = 'start' | 'end';
interface PopoverProps {
  /**
   * Render prop for the trigger element.
   * The open state is passed so the trigger can reflect aria-expanded.
   */
  trigger: (props: {
    open: boolean;
    toggle: () => void;
  }) => React$1.ReactNode;
  /**
   * Render prop for the panel content.
   * `close` is a callback to close the popover.
   */
  panel: (props: {
    close: () => void;
  }) => React$1.ReactNode;
  /**
   * Horizontal alignment of the panel relative to the container.
   * - 'end' (default): right-aligns the panel (panel right = container right)
   * - 'start': left-aligns the panel (panel left = container left)
   */
  align?: PopoverAlign;
  /** Accessible label for the panel dialog wrapper. */
  ariaLabel?: string;
  className?: string;
}
declare const Popover: ({
  trigger,
  panel,
  align,
  ariaLabel,
  className
}: PopoverProps) => React$1.JSX.Element;
//#endregion
//#region assets/ui/useFloatingPanel.d.ts
interface UseFloatingPanelOptions {
  /** Called when the panel is closed by click-outside or Escape. */
  onClose?: () => void;
}
interface UseFloatingPanelResult {
  open: boolean;
  setOpen: (open: boolean) => void;
  toggle: () => void;
  /** Attach this ref to the element that contains both trigger and panel. */
  containerRef: React.RefObject<HTMLDivElement | null>;
}
declare function useFloatingPanel(options?: UseFloatingPanelOptions): UseFloatingPanelResult;
//#endregion
export { ACCENT_PRESETS, type AccentPreset, AppDialog, type AppDialogProps, AppDropdown, type AppDropdownOption, type AppDropdownProps, type AppDropdownVariant, AppToolbar, type AppToolbarProps, Avatar, type AvatarProps, type AvatarShape, type AvatarSize, Badge, type BadgeProps, type BadgeSize, type BadgeVariant, Button, type ButtonProps, type ButtonSize, type ButtonVariant, Card, type CardProps, Chip, type ChipProps, CollapsibleSection, type CollapsibleSectionProps, ConfirmDialog, type ConfirmDialogProps, ContextMenu, type ContextMenuItem, type ContextMenuProps, DatePicker, type DatePickerProps, DateRangePicker, type DateRangePickerProps, type Density, DensityContext, type DensityContextValue, DensityProvider, EmptyState, type EmptyStateProps, FormField, type FormFieldProps, IconButton, type IconButtonProps, Popover, type PopoverAlign, type PopoverProps, SelectField, type SelectFieldProps, SettingsPanel, type SettingsPanelProps, Skeleton, type SkeletonProps, StatCard, type StatCardProps, TextAreaField, type TextAreaFieldProps, TextField, type TextFieldProps, type Theme, ThemeContext, type ThemeContextValue, type ThemeMode, ThemeProvider, type ThemeProviderProps, ThemeSwitcher, type ThemeSwitcherProps, Toggle, type ToggleProps, type UseFloatingPanelOptions, type UseFloatingPanelResult, getAvatarHue, getAvatarInitials, useAccentColor, useDensity, useFloatingPanel, useTheme };