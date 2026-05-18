import type { MutableRefObject } from 'react';
import type { FormRef } from 'devextreme-react/form';

import type { DataRecord } from '@core/data/DataRecord';
import type { Field } from '@core/field/Field';
import type { FormRemoteOptions } from '@core/form/FormRemoteOptions';
import { maybeAutoSelectSingleValue } from './DxFormRemoteAutoSelect';
import { getDxRemoteOptions } from './DxFormRemoteOptions';
import { getDxRemoteStore } from './DxFormRemoteStore';

export function getDxRemoteEditorOptions(
  field: Field,
  remoteOptions: FormRemoteOptions | null,
  formRef: MutableRefObject<FormRef | null>,
): DataRecord {
  if (!remoteOptions) {
    return {};
  }

  // Create a store instance so we can query a small page to decide auto-selection.
  const store = getDxRemoteStore(remoteOptions);

  maybeAutoSelectSingleValue(field, remoteOptions, store, formRef);

  return getDxRemoteOptions(remoteOptions, store);
}
