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
 * When `formData` is `null`, SmartCrud has not hydrated the form snapshot yet
 * (typically while opening an edit dialog), so rules are skipped and defaults
 * are returned. Empty objects `{}` are treated as valid create-form state.
 */
export function useConditionalRules(
  fields: Field[],
  formData: Record<string, unknown> | null,
): FieldState[] {
  return useMemo(() => {
    return fields.map((field) => {
      const baseVisible = field.visibleOnForm;
      if (formData == null) {
        return {
          name: field.name,
          visible: baseVisible,
          disabled: false,
          computedValue: undefined,
        };
      }

      const visible = field.visibleWhen ? baseVisible && field.visibleWhen(formData) : baseVisible;
      const disabled = field.disabledWhen ? field.disabledWhen(formData) : false;
      const computedValue = field.computed ? field.computed(formData) : undefined;
      return { name: field.name, visible, disabled, computedValue };
    });
  }, [fields, formData]);
}
