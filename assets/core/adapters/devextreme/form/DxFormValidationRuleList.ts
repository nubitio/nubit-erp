import type { Field } from '@core/field/Field';
import type { ValidationRule } from '@core/field/ValidationRule';

export function getDxValidationRuleList(field: Field): ValidationRule[] {
  // Skip validation rules for hidden fields to avoid blocking form submission
  // when a field is conditionally hidden via visibleWhen or visible(false).
  if (field.visibleOnForm === false || field.visible === false) {
    return [];
  }

  let validationRules: ValidationRule[] = [];
  if (field.required) {
    validationRules.push({
      type: 'required',
      options: {
        message: `${field.label} es requerido`,
      },
    });
  }

  return validationRules.concat(field.validators ?? []);
}
