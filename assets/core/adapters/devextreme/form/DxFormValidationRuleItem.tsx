import {
  CompareRule,
  CustomRule,
  EmailRule,
  PatternRule,
  RangeRule,
  RequiredRule,
  StringLengthRule,
} from 'devextreme-react/form';

import type { ValidationRule } from '@core/field/ValidationRule';

export function buildValidationRule(rule: ValidationRule, index: number) {
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
      return <RangeRule key={index} min={rule.options.min} max={rule.options.max} message={rule.options.message} />;
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
}
