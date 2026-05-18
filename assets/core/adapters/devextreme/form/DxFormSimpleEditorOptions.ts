import type { MutableRefObject } from 'react';
import type { FormRef } from 'devextreme-react/form';

import type { DataRecord } from '@core/data/DataRecord';
import type { Field } from '@core/field/Field';
import type { FormSimpleEditorOptions } from '@core/form/FormSimpleEditorOptions';
import type { UploadedFile } from '@core/form/UploadedFile';
import { getDxBasicEditorOptions } from './DxFormBasicEditorOptions';
import { getDxFileEditorOptions } from './DxFormFileEditorOptions';
import { getDxPasswordEditorOptions } from './DxFormPasswordEditorOptions';

export const getDxSimpleEditorOptions = (
  options: FormSimpleEditorOptions,
  field: Field,
  formRef: MutableRefObject<FormRef | null>,
  uploadedFiles: MutableRefObject<UploadedFile[]>,
): DataRecord => ({
  ...getDxBasicEditorOptions(options),
  ...(options.password ? getDxPasswordEditorOptions(field, formRef) : {}),
  ...(options.file ? getDxFileEditorOptions(field, options.file, uploadedFiles) : {}),
});
