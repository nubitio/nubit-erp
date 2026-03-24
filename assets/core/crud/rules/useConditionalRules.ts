import { useMemo } from 'react';
import type { Field } from '../../field/Field';

export interface FieldState {
  name: string;
  visible: boolean;
  disabled: boolean;
  computedValue?: unknown;
}

/**
 * Given the current Field definitions and live form data,
 * returns the reactive state (visible/disabled/computedValue) for each field.
 *
 * Pure computation — re-runs whenever formData changes.
 *
 * When `formData` is an empty object `{}` (the initial state before CrudPage's
 * onFieldDataChanged has fired), all rules are skipped and defaults are returned.
 * This prevents a visibility/disabled flicker on dialog open (BUG-009).
 */
export function useConditionalRules(
  fields: Field[],
  formData: Record<string, unknown>,
): FieldState[] {
  return useMemo(() => {
    // Guard: skip rule evaluation while formData has not yet been populated.
    // This suppresses the one-render flash where SmartCrudPage initialises
    // formData to {} before the form's onFieldDataChanged fires.
    const hasData = Object.keys(formData).length > 0;

    return fields.map((field) => {
      const baseVisible = field.visibleOnForm;
      const visible =
        hasData && field.visibleWhen ? baseVisible && field.visibleWhen(formData) : baseVisible;
      const disabled = hasData && field.disabledWhen ? field.disabledWhen(formData) : false;
      const computedValue = hasData && field.computed ? field.computed(formData) : undefined;
      return { name: field.name, visible, disabled, computedValue };
    });
  }, [fields, formData]);
}
