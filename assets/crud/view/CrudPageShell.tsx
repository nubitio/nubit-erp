import React from 'react';
import { Button, IconButton } from '@nubit/ui';
import { useCoreTranslation } from '@nubit/core';
import type { CrudFormShellProps } from './CrudFormShellProps';
import './CrudPageShell.scss';

/**
 * Page form shell — renders inline (no portal) and is expected to replace the
 * grid view via the `wrapper--with-page` CSS hook set by `CrudPage`.
 *
 * URL deep-linking is already wired through `useRouting`; the shell itself
 * does not navigate — it just exposes Back/Cancel/Save callbacks that the
 * orchestrator maps to `navigate()` calls or close-dialog dispatches.
 */
export const CrudPageShell: React.FC<CrudFormShellProps> = ({
  isOpen,
  title,
  onClose,
  onCancel,
  onSave,
  children,
}) => {
  const { t } = useCoreTranslation();
  if (!isOpen) return null;

  return (
    <section className="crud-page-shell" aria-label={title}>
      <header className="crud-page-shell__header">
        <IconButton
          className="crud-page-shell__back"
          icon="ph ph-arrow-left"
          label={t('dialog.buttonCancel')}
          onClick={onClose}
        />
        <h1 className="crud-page-shell__title">{title}</h1>
      </header>
      <div className="crud-page-shell__body">{children}</div>
      <footer className="crud-page-shell__footer">
        <Button variant="secondary" onClick={onCancel}>
          {t('dialog.buttonCancel')}
        </Button>
        <Button variant="primary" className="dialog-save-button" onClick={onSave}>
          <span>{t('dialog.buttonSave')}</span>
        </Button>
      </footer>
    </section>
  );
};
