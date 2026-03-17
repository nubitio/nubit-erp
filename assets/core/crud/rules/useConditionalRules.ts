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
 */
export function useConditionalRules(
  fields: Field[],
  formData: Record<string, unknown>,
): FieldState[] {
  return useMemo(() => {
    return fields.map((field) => {
      const visible = field.visibleWhen ? field.visibleWhen(formData) : true;
      const disabled = field.disabledWhen ? field.disabledWhen(formData) : false;
      const computedValue = field.computed ? field.computed(formData) : undefined;
      return { name: field.name, visible, disabled, computedValue };
    });
  }, [fields, formData]);
}
