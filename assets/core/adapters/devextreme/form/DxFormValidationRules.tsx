import {
  CompareRule,
  CustomRule,
  EmailRule,
  PatternRule,
  RangeRule,
  RequiredRule,
  StringLengthRule,
} from 'devextreme-react/form';

import type { Field } from '@core/field/Field';
import type { ValidationRule } from '@core/field/ValidationRule';

export const buildValidationRules = (field: Field) => {
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

  validationRules = validationRules.concat(field.validators ?? []);
  return validationRules.map((rule, index) => {
    switch (rule.type) {
      case 'required':
        return <RequiredRule key={index} />;
      case 'pattern':
        return <PatternRule key={index} pattern={rule.options.pattern} message={rule.options.message} />;
      case 'email':
        return <EmailRule key={index} message={rule.options.message} />;
      case 'compare':
        return (
          <CompareRule
            key={index}
            comparisonTarget={rule.options.comparisonTarget}
            comparisonType={rule.options.comparisonType}
            message={rule.options.message}
          />
        );
      case 'stringLength':
        return (
          <StringLengthRule
            key={index}
            min={rule.options.min}
            max={rule.options.max}
            message={rule.options.message}
          />
        );
      case 'range':
        return (
          <RangeRule
            key={index}
            min={rule.options.min}
            max={rule.options.max}
            message={rule.options.message}
          />
        );
      case 'custom':
        return (
          <CustomRule
            key={index}
            validationCallback={rule.options.validationCallback}
            message={rule.options.message}
          />
        );
      default:
        return null;
    }
  });
};
