import type { Field } from '@core/field/Field';
import { buildValidationRule } from './DxFormValidationRuleItem';
import { getDxValidationRuleList } from './DxFormValidationRuleList';

export const buildValidationRules = (field: Field) => {
  return getDxValidationRuleList(field).map(buildValidationRule);
};
