import { useRef, useMemo } from 'react';
import { createScopedEventBus } from '../event/createScopedEventBus';
import { usePermissions } from './usePermissions';
import { useSelectionState } from './useSelectionState';
import { useColumnPreset } from './useColumnPreset';
import type { Field } from '../field/Field';
import type { DxFormEvents } from '../form/DxForm';
import type { FormEventNames } from '../event/EventTypes';
import type { ResourceConfig, FieldsFactory } from './ResourceConfig';
import type { ResolvedPermissions } from './usePermissions';
import type { SelectionState } from './useSelectionState';
import type { ColumnPresetState } from './useColumnPreset';

export function useCrudPage<T extends Record<string, unknown> = Record<string, unknown>>(
  resource: ResourceConfig<T>,
): {
  events: FormEventNames;
  fields: Field[];
  formRef: React.RefObject<DxFormEvents | null>;
  permissions: ResolvedPermissions;
  selectionState: SelectionState & {
    onSelectionChanged: (rows: Record<string, unknown>[]) => void;
  };
  presetState: ColumnPresetState;
} {
  const events = useMemo(
    () => resource.events ?? createScopedEventBus(resource.id),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [resource.id],
  );
  const formRef = useRef<DxFormEvents | null>(null);
  const fields = useMemo(() => {
    if (typeof resource.fields === 'function') {
      return (resource.fields as FieldsFactory<T>)(formRef) as unknown as Field[];
    }
    return resource.fields as unknown as Field[];
  }, [resource.fields]); // eslint-disable-line react-hooks/exhaustive-deps
  const permissions = usePermissions(resource as ResourceConfig);

  const identityFieldName = useMemo(() => {
    const resolved =
      typeof resource.fields === 'function' ? fields : (resource.fields as unknown as Field[]);
    const identityField = resolved.find((f) => f.isIdentity);
    return identityField?.name ?? 'id';
  }, [fields, resource.fields]); // eslint-disable-line react-hooks/exhaustive-deps

  const selectionState = useSelectionState(identityFieldName);
  const presetState = useColumnPreset(resource as ResourceConfig);

  return { events, fields, formRef, permissions, selectionState, presetState };
}
