import React from "react";
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
export { AppDialog, type AppDialogProps, ConfirmDialog, type ConfirmDialogProps };