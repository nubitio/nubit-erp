import { useId, type ReactNode } from 'react';
import './EntityForm.scss';

/** Attributes a control needs so its label, hint and error are announced together. */
export interface FieldControlProps {
  id: string;
  'aria-invalid': true | undefined;
  'aria-required': true | undefined;
  'aria-describedby': string | undefined;
}

export interface FieldProps {
  label: string;
  required?: boolean;
  /** Always-visible guidance, linked to the control with `aria-describedby`. */
  hint?: string;
  error?: string;
  /** Full row on wide layouts. */
  wide?: boolean;
  children: (control: FieldControlProps) => ReactNode;
}

/**
 * Label + control + hint + error for one field.
 *
 * The label is a sibling of the control, not a wrapper around it, so the control's accessible
 * name is exactly the label text. The required marker is drawn by CSS (`data-required`) with an
 * empty alternative text, so it is neither part of the label's text nor of its accessible name;
 * `aria-required` tells assistive technology instead. (ui's `FormField` wraps everything in the `<label>`, which makes
 * the hint and the error part of the name.) The hint and error are linked with
 * `aria-describedby`, in that order, and the error is announced when it appears.
 */
export function Field({ label, required, hint, error, wide, children }: FieldProps) {
  const id = useId();
  const hintId = hint ? `${id}-hint` : undefined;
  const errorId = error ? `${id}-error` : undefined;
  const describedBy = [hintId, errorId].filter(Boolean).join(' ') || undefined;

  return (
    <div
      className={`erp-field${wide ? ' erp-field--wide' : ''}`}
      data-invalid={error ? true : undefined}
    >
      <label htmlFor={id} className="erp-field__label" data-required={required ? true : undefined}>
        {label}
      </label>
      {children({
        id,
        'aria-invalid': error ? true : undefined,
        'aria-required': required ? true : undefined,
        'aria-describedby': describedBy,
      })}
      {hint && (
        <p id={hintId} className="erp-field__hint">
          {hint}
        </p>
      )}
      {error && (
        <p id={errorId} className="erp-field__error" role="alert">
          {error}
        </p>
      )}
    </div>
  );
}

export function FormSection({ title, children }: { title: string; children: ReactNode }) {
  return (
    <fieldset className="erp-form-section">
      <legend>{title}</legend>
      <div className="erp-form-section__grid">{children}</div>
    </fieldset>
  );
}
