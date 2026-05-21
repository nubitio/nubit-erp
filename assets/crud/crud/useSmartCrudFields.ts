import { useMemo } from 'react';
import type { Field } from '../field/Field';
import { useFieldPermissions } from './rules/useFieldPermissions';
import { useConditionalRules } from './rules/useConditionalRules';
import { useDependsOn } from './rules/useDependsOn';
import {
  applyFieldOperationSemantics,
  type SmartCrudFieldOperationBehavior,
  type SmartCrudOperation,
} from './fieldOperationSemantics';
import type { FormDataRecord } from '../form/FormDataSnapshot';

type SmartCrudRuntimeField = Field & {
  operation?: SmartCrudFieldOperationBehavior;
};

export interface SmartCrudFieldsResult {
  processedFields: Field[];
  computedValues: FormDataRecord;
}

/**
 * Runs the full SmartCrud field processing pipeline:
 *   1. Operation semantics — adjusts visibility/required/readonly per create/edit
 *   2. RBAC — filters or disables fields based on user roles
 *   3. Conditional rules — evaluates visibleWhen / disabledWhen / computed
 *   4. Dependency invalidation — refetches dependent field options on change
 *   5. Field state merge — applies conditional state back to field definitions
 *
 * Extracted from SmartCrudPage to isolate the declarative rules pipeline
 * from schema loading and routing concerns.
 */
export function useSmartCrudFields(
  fields: Field[],
  activeOperation: SmartCrudOperation | null,
  formData: FormDataRecord | null,
  roles: string[],
): SmartCrudFieldsResult {
  // 1. Apply operation-aware semantics (create-only / edit-only fields, etc.)
  const operationAwareFields = useMemo(() => {
    if (!activeOperation) return fields;

    return fields.map((field) => {
      const runtimeField = field as SmartCrudRuntimeField;
      if (!runtimeField.operation) return field;

      return applyFieldOperationSemantics(
        runtimeField,
        activeOperation,
        runtimeField.operation,
        `Field '${field.name}'`,
        'form',
      ) as Field;
    });
  }, [activeOperation, fields]);

  // 2. RBAC field filtering
  const permissionFilteredFields = useFieldPermissions(operationAwareFields, roles);

  // 3. Conditional rules (visibleWhen / disabledWhen / computed)
  const fieldStates = useConditionalRules(permissionFilteredFields, formData);

  // 4. Dependency invalidation (side effect)
  useDependsOn(permissionFilteredFields, formData);

  // 5. Merge conditional state back into field definitions
  const processedFields = useMemo(
    () =>
      permissionFilteredFields.map((field) => {
        if (field.isIdentity) return field;

        const state = fieldStates.find((s) => s.name === field.name);
        if (!state) return field;

        const updated: Field = { ...field };

        // Conditional visibility affects the dialog form only — grid column
        // visibility stays controlled by the static field definition.
        updated.visibleOnForm = state.visible;
        updated.hidden = !state.visible;

        if (state.disabled) {
          updated.disabled = true;
        }

        if (state.computedValue !== undefined) {
          updated.readonly = true;
        }

        return updated;
      }),
    [permissionFilteredFields, fieldStates],
  );

  // 6. Collect computed values for form synchronization
  const computedValues = useMemo(
    () =>
      fieldStates.reduce<FormDataRecord>((acc, state) => {
        if (state.computedValue !== undefined) {
          acc[state.name] = state.computedValue;
        }
        return acc;
      }, {}),
    [fieldStates],
  );

  return { processedFields, computedValues };
}
