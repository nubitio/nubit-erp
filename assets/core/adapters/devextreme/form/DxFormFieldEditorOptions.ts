import type { MutableRefObject } from 'react';
import type { FormRef } from 'devextreme-react/form';

import type { DataRecord } from '@core/data/DataRecord';
import type { Field } from '@core/field/Field';
import { getFormRemoteOptions } from '@core/form/FormRemoteOptions';
import { getFormSimpleEditorOptions } from '@core/form/FormSimpleEditorOptions';
import type { UploadedFile } from '@core/form/UploadedFile';
import { getDxCommonEditorOptions } from './DxFormCommonEditorOptions';
import { getDxSimpleEditorOptions } from './DxFormEditorOptions';
import { getDxFieldPresentation } from './DxFormFieldPresentation';
import { getDxRemoteEditorOptions } from './DxFormRemoteEditorOptions';

export function getDxFieldEditorOptions(
  field: Field,
  isEditable: boolean,
  uploadedFiles: MutableRefObject<UploadedFile[]>,
  formRef: MutableRefObject<FormRef | null>,
): DataRecord {
  const remoteOptions = getDxRemoteEditorOptions(field, getFormRemoteOptions(field), formRef);
  const simpleOptions = getDxSimpleEditorOptions(
    getFormSimpleEditorOptions(field),
    field,
    formRef,
    uploadedFiles,
  );
  const baseOptions = {
    ...remoteOptions,
    ...simpleOptions,
  };
  const commonOptions = getDxCommonEditorOptions(
    field,
    isEditable,
    formRef,
    (baseOptions.buttons as unknown[]) ?? [],
  );
  const presentation = getDxFieldPresentation(field);

  return {
    ...baseOptions,
    ...commonOptions,
    ...presentation.editorOptions,
  };
}
