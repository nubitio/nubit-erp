import React, { useEffect, useId, useRef } from 'react';
import { createPortal } from 'react-dom';
import { Button, IconButton } from '@nubit/ui';
import { useCoreTranslation } from '@nubit/core';
import type { CrudFormShellProps } from './CrudFormShellProps';
import { DEFAULT_DRAWER_WIDTH } from './drawerSizes';
import './CrudDrawerShell.scss';

const formatSize = (value?: number | string) => {
  if (value === undefined) return undefined;
  return typeof value === 'number' ? `${value}px` : value;
};

/**
 * Drawer form shell — slides in from the side and leaves the grid visible.
 *
 * - Default: right-side panel `min(880px, 60vw)` wide.
 * - Mobile (≤ 768px): becomes a full-screen sheet (no side gap, no animation jank).
 * - Escape closes the drawer; nested lookup dialogs float above via z-index.
 */
export const CrudDrawerShell: React.FC<CrudFormShellProps> = ({
  isOpen,
  mode,
  title,
  onClose,
  onCancel,
  onSave,
  drawerWidth = DEFAULT_DRAWER_WIDTH,
  drawerSide = 'right',
  children,
}) => {
  const { t } = useCoreTranslation();
  const titleId = useId();
  const panelRef = useRef<HTMLDivElement | null>(null);
  const lastFocusedElementRef = useRef<HTMLElement | null>(null);

  // Move focus into the drawer when it opens and restore on close.
  useEffect(() => {
    if (!isOpen) return;
    lastFocusedElementRef.current = document.activeElement as HTMLElement | null;
    window.setTimeout(() => {
      const focusable = panelRef.current?.querySelector<HTMLElement>(
        'button:not(:disabled), [href], input:not(:disabled), select:not(:disabled), textarea:not(:disabled), [tabindex]:not([tabindex="-1"])',
      );
      focusable?.focus();
    });
    return () => lastFocusedElementRef.current?.focus?.();
  }, [isOpen]);

  // Escape closes the drawer (matches dialog UX). Confirm-on-dirty is handled
  // upstream — the orchestrator decides whether to actually close.
  useEffect(() => {
    if (!isOpen) return;
    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        onClose();
      }
    };
    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [isOpen, onClose]);

  return createPortal(
    <div
      className={[
        'crud-drawer-root',
        isOpen && 'crud-drawer-root--open',
        `crud-drawer-root--${drawerSide}`,
      ]
        .filter(Boolean)
        .join(' ')}
      aria-hidden={!isOpen}
      style={{ pointerEvents: isOpen ? undefined : 'none' }}
    >
      <button
        className="crud-drawer__scrim"
        type="button"
        aria-label={t('dialog.buttonCancel')}
        onClick={onClose}
        tabIndex={-1}
      />
      <aside
        ref={panelRef}
        className="crud-drawer"
        role="dialog"
        aria-modal="false"
        aria-labelledby={titleId}
        style={{ width: formatSize(drawerWidth) }}
      >
        <header className="crud-drawer__header">
          <h2 className="crud-drawer__title" id={titleId}>
            {title}
          </h2>
          <IconButton
            className="crud-drawer__close"
            icon="ph ph-x"
            label={t('dialog.buttonCancel')}
            onClick={onClose}
          />
        </header>
        <div className="crud-drawer__body">{children}</div>
        <footer className="crud-drawer__footer">
          <Button variant="secondary" onClick={onCancel}>
            {t('dialog.buttonCancel')}
          </Button>
          {mode !== 'view' && (
            <Button variant="primary" className="dialog-save-button" onClick={onSave}>
              <span>{t('dialog.buttonSave')}</span>
            </Button>
          )}
        </footer>
      </aside>
    </div>,
    document.body,
  );
};
