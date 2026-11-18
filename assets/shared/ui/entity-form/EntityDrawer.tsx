import { Button, Drawer } from '@nubitio/ui';
import { useId, type FormEventHandler, type ReactNode } from 'react';
import './EntityForm.scss';

export interface EntityDrawerProps {
  title: string;
  isOpen: boolean;
  onClose: () => void;
  onSubmit: FormEventHandler<HTMLFormElement>;
  /** A save is in flight: the form cannot be dismissed or submitted again. */
  busy: boolean;
  submitLabel: string;
  cancelLabel?: string;
  /** Something that went wrong with the save as a whole (not a single field). */
  formError?: string | null;
  children: ReactNode;
}

/**
 * Side panel with a form: Save/Cancel in a sticky footer, no dismissal while saving.
 *
 * The form has `noValidate`: validation belongs to the form library, which also focuses the first
 * invalid field, instead of the browser's native bubbles.
 */
export function EntityDrawer({
  title,
  isOpen,
  onClose,
  onSubmit,
  busy,
  submitLabel,
  cancelLabel = 'Cancelar',
  formError,
  children,
}: EntityDrawerProps) {
  const formId = useId();

  return (
    <Drawer
      isOpen={isOpen}
      onClose={onClose}
      busy={busy}
      title={title}
      width={480}
      footer={
        <div className="erp-form-footer">
          <Button type="button" variant="secondary" onClick={onClose} disabled={busy}>
            {cancelLabel}
          </Button>
          <Button type="submit" form={formId} disabled={busy} loading={busy}>
            {submitLabel}
          </Button>
        </div>
      }
    >
      <form id={formId} noValidate onSubmit={onSubmit} className="erp-form">
        {formError && (
          <p role="alert" className="erp-form__error">
            {formError}
          </p>
        )}
        {children}
      </form>
    </Drawer>
  );
}
