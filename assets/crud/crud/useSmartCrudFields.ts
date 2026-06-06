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
  /** Stable field set for the grid — RBAC + operation semantics only. */
  gridFields: Field[];
  /** Reactive field set for the form — includes conditional rules. */
  processedFields: Field[];
  computedValues: FormDataRecord;
}

/**
 * Runs the full SmartCrud field processing pipeline:
 *   1. Operation semantics — adjusts visibility/required/readonly per create/edit
 *   2. RBAC — filters or disables fields based on user roles
 *   3. Conditional rules — evaluates visibleWhen / disabledWhen / requiredWhen / defaultWhen / computed
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

  // 3. Conditional rules (visibleWhen / disabledWhen / requiredWhen / defaultWhen / computed)
  const fieldStates = useConditionalRules(permissionFilteredFields, formData);

  // 4. Dependency invalidation (side effect)
  useDependsOn(permissionFilteredFields, formData);

  // 5. Merge conditional state back into field definitions
  const processedFields = useMemo(() => {
    const stateByName = new Map(fieldStates.map((s) => [s.name, s]));
    return permissionFilteredFields.map((field) => {
      if (field.isIdentity) return field;

      const state = stateByName.get(field.name);
      if (!state) return field;

      const updated: Field = { ...field };

      // Conditional visibility affects the form only — never the grid columns.
      updated.visibleOnForm = state.visible;

      if (state.disabled) {
        updated.disabled = true;
      }

      updated.required = state.required;

      if (state.computedValue !== undefined) {
        updated.readonly = true;
      }

      return updated;
    });
  }, [permissionFilteredFields, fieldStates]);

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

  return {
    gridFields: permissionFilteredFields,
    processedFields,
    computedValues,
  };
}
