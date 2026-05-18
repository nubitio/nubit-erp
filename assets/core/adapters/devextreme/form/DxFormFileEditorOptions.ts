import type { MutableRefObject } from 'react';

import type { DataRecord } from '@core/data/DataRecord';
import type { Field } from '@core/field/Field';
import type { FormFileEditorOptions } from '@core/form/FormSimpleEditorOptions';
import type { UploadedFile } from '@core/form/UploadedFile';
import { getIRI } from '@shared/lib/SharedUtils';

export function getDxFileEditorOptions(
  field: Field,
  options: FormFileEditorOptions,
  uploadedFiles: MutableRefObject<UploadedFile[]>,
): DataRecord {
  return {
    accept: options.accept,
    ...(options.uploadUrl === undefined
      ? { uploadMode: 'useForm' }
      : {
          uploadUrl: options.uploadUrl,
          name: 'file',
          onBeforeSend: () => {},
          onUploaded: (e: any) => {
            const response = JSON.parse(e.request.response);
            uploadedFiles.current.push({
              name: field.name,
              iri: getIRI(options.uploadUrl ?? '', response.id.toString()),
            });
          },
        }),
    selectButtonText: options.selectButtonText,
  };
}
