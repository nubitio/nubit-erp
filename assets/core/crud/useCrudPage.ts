import { useRef, useMemo } from 'react';
import { usePermissions } from './usePermissions';
import { useSelectionState } from './useSelectionState';
import { useColumnPreset } from './useColumnPreset';
import type { Field } from '../field/Field';
import type { FormHandle } from '../form/FormHandle';
import type { FormEventNames } from '../event/EventTypes';
import type { ResourceConfig, FieldsFactory } from './ResourceConfig';
import type { ResolvedPermissions } from './usePermissions';
import type { SelectionState } from './useSelectionState';
import type { ColumnPresetState } from './useColumnPreset';
import { resolveCrudResource, type ResolvedCrudResource } from './resolveCrudResource';
import type { DataRecord } from '../data/DataRecord';

export function useCrudPage<T extends DataRecord = DataRecord>(
  resource: ResourceConfig<T>,
): {
  events: FormEventNames;
  resource: ResolvedCrudResource<T>;
  fields: Field[];
  formRef: React.RefObject<FormHandle | null>;
  permissions: ResolvedPermissions;
  selectionState: SelectionState & {
    onSelectionChanged: (rows: DataRecord[]) => void;
  };
  presetState: ColumnPresetState;
} {
  const resolvedResource = useMemo(() => resolveCrudResource(resource), [resource]);
  const events = resolvedResource.events;
  const formRef = useRef<FormHandle | null>(null);
  const fields = useMemo(() => {
    if (typeof resolvedResource.fields === 'function') {
      // eslint-disable-next-line react-hooks/refs -- fields factories intentionally receive the live form ref contract
      return (resolvedResource.fields as FieldsFactory<T>)(formRef) as unknown as Field[];
    }
    return resolvedResource.fields as unknown as Field[];
  }, [resolvedResource.fields]); // eslint-disable-line react-hooks/exhaustive-deps
  const permissions = usePermissions(
    resolvedResource as ResourceConfig,
    resolvedResource._supportedOperations ?? [],
  );

  const identityFieldName = useMemo(() => {
    const resolved =
      typeof resolvedResource.fields === 'function'
        ? fields
        : (resolvedResource.fields as unknown as Field[]);
    const identityField = resolved.find((f) => f.isIdentity);
    return identityField?.name ?? 'id';
  }, [fields, resolvedResource.fields]); // eslint-disable-line react-hooks/exhaustive-deps

  const selectionState = useSelectionState(identityFieldName);
  const presetState = useColumnPreset(resolvedResource as ResourceConfig);

  return {
    events,
    resource: resolvedResource,
    fields,
    formRef,
    permissions,
    selectionState,
    presetState,
  };
}
