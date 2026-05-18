import type { MutableRefObject } from 'react';
import type { FormRef } from 'devextreme-react/form';

import type { DataRecord } from '@core/data/DataRecord';
import type { Field } from '@core/field/Field';
import type { FormRemoteOptions } from '@core/form/FormRemoteOptions';
import { setDxEditorOption } from './DxFormAccessorReads';

export function maybeAutoSelectSingleValue(
  field: Field,
  remoteOptions: FormRemoteOptions,
  store: unknown,
  formRef: MutableRefObject<FormRef | null>,
): void {
  try {
    if (!remoteOptions.autoSelectIfSingle || remoteOptions.defaultValue !== undefined) {
      return;
    }

    if (field.mode !== 'remote' && remoteOptions.localData.length === 1) {
      const single = remoteOptions.localData[0];
      const value = remoteOptions.multiple
        ? [single[remoteOptions.valueField]]
        : single[remoteOptions.valueField];
      field.defaultValue = value as Field['defaultValue'];
      setDxEditorOption(formRef, field.name, 'value', value);
      return;
    }

    if (!remoteOptions.url) {
      return;
    }

    // For remote stores, load up to 2 items so we can detect exactly one without fetching all.
    (store as { load: (options: { skip: number; take: number }) => Promise<unknown> })
      .load({ skip: 0, take: 2 })
      .then((result: unknown) => {
        const items = extractRemoteItems(result);
        if (items.length !== 1) {
          return;
        }

        const single = items[0];
        const value = remoteOptions.multiple
          ? [single[remoteOptions.valueField]]
          : single[remoteOptions.valueField];
        field.defaultValue = value as Field['defaultValue'];
        setDxEditorOption(formRef, field.name, 'value', value);
      })
      .catch((err: unknown) => {
        console.warn(`[DevExtremeForm] auto-select failed for "${field.name}":`, err);
      });
  } catch (err) {
    console.warn(`[DevExtremeForm] auto-select setup failed for "${field.name}":`, err);
  }
}

function extractRemoteItems(result: unknown): DataRecord[] {
  if (Array.isArray(result)) {
    return result as DataRecord[];
  }

  if (
    result !== null &&
    typeof result === 'object' &&
    'data' in result &&
    Array.isArray((result as { data?: unknown }).data)
  ) {
    return (result as { data: DataRecord[] }).data;
  }

  return [];
}
