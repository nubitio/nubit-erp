import React, { useEffect, useId, useRef } from 'react';
import { createPortal } from 'react-dom';
import { IconButton } from './Button';
import './Drawer.scss';

export type DrawerSide = 'right' | 'left';

export interface DrawerProps {
  /** Controls visibility. The drawer mounts/unmounts with this flag. */
  isOpen: boolean;
  /** Called on Escape, scrim click, or the built-in close button. */
  onClose: () => void;
  /** Optional title rendered in the header. */
  title?: React.ReactNode;
  /** Width of the panel. Accepts any CSS value or a number (px). */
  width?: number | string;
  /** Which side the drawer slides in from. Default: right. */
  side?: DrawerSide;
  /**
   * Scrim opacity level.
   * - 'subtle'  — dimly tints background (default, keeps content legible)
   * - 'overlay' — solid overlay, same as modal backdrop
   */
  scrim?: 'subtle' | 'overlay';
  /** Content rendered inside the scrollable body. */
  children: React.ReactNode;
  /** Optional sticky footer (e.g. action buttons). */
  footer?: React.ReactNode;
  /** Extra class applied to the panel element. */
  className?: string;
  /** aria-label for the dialog when no title is provided. */
  'aria-label'?: string;
}

const formatWidth = (value?: number | string) => {
  if (value === undefined) return undefined;
  return typeof value === 'number' ? `${value}px` : value;
};

export const Drawer: React.FC<DrawerProps> = ({
  isOpen,
  onClose,
  title,
  width,
  side = 'right',
  scrim = 'subtle',
  children,
  footer,
  className,
  'aria-label': ariaLabel,
}) => {
  const titleId = useId();
  const panelRef = useRef<HTMLDivElement | null>(null);
  const lastFocusRef = useRef<HTMLElement | null>(null);

  // Focus management
  useEffect(() => {
    if (!isOpen) return;
    lastFocusRef.current = document.activeElement as HTMLElement | null;
    const timer = window.setTimeout(() => {
      const focusable = panelRef.current?.querySelector<HTMLElement>(
        'button:not(:disabled), [href], input:not(:disabled), select:not(:disabled), textarea:not(:disabled), [tabindex]:not([tabindex="-1"])',
      );
      focusable?.focus();
    });
    return () => {
      clearTimeout(timer);
      lastFocusRef.current?.focus?.();
    };
  }, [isOpen]);

  // Escape key
  useEffect(() => {
    if (!isOpen) return;
    const handler = (e: KeyboardEvent) => {
      if (e.key === 'Escape') { e.preventDefault(); onClose(); }
    };
    document.addEventListener('keydown', handler);
    return () => document.removeEventListener('keydown', handler);
  }, [isOpen, onClose]);

  return createPortal(
    <div
      className={[
        'ef-drawer-root',
        isOpen && 'ef-drawer-root--open',
        `ef-drawer-root--${side}`,
      ].filter(Boolean).join(' ')}
      aria-hidden={!isOpen}
      style={{ pointerEvents: isOpen ? undefined : 'none' }}
    >
      {/* Scrim */}
      <button
        className={`ef-drawer__scrim ef-drawer__scrim--${scrim}`}
        type="button"
        aria-label="Cerrar panel"
        tabIndex={-1}
        onClick={onClose}
      />

      {/* Panel */}
      <aside
        ref={panelRef}
        className={['ef-drawer', className].filter(Boolean).join(' ')}
        role="dialog"
        aria-modal="false"
        aria-labelledby={title ? titleId : undefined}
        aria-label={!title ? ariaLabel : undefined}
        style={{ width: formatWidth(width) }}
      >
        {/* Header */}
        {(title !== undefined) && (
          <header className="ef-drawer__header">
            <h2 className="ef-drawer__title" id={titleId}>{title}</h2>
            <IconButton
              className="ef-drawer__close"
              icon="ph ph-x"
              label="Cerrar"
              onClick={onClose}
            />
          </header>
        )}

        {/* Body */}
        <div className="ef-drawer__body">{children}</div>

        {/* Footer */}
        {footer && <footer className="ef-drawer__footer">{footer}</footer>}
      </aside>
    </div>,
    document.body,
  );
};
