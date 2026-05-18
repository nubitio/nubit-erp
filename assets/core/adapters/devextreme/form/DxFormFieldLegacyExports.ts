import type { Field } from '@core/field/Field';
import { getFormEditorKind } from '@core/form/FormEditorKind';
import { getDxEditorType } from './DxFormEditorType';
import { buildValidationRules } from './DxFormValidationRules';

// Exported for testing/legacy shims only — not part of the runtime renderer API.
export const _getEditorType = (field: Field) => getDxEditorType(getFormEditorKind(field));
export const _buildValidationRules = buildValidationRules;
