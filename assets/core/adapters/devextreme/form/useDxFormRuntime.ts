import { useRef } from 'react';
import { FormRef } from 'devextreme-react/form';
import { DataGridRef } from 'devextreme-react/data-grid';
import ArrayStore from 'devextreme/data/array_store';
import DataSource from 'devextreme/data/data_source';

import type { Field } from '@core/field/Field';
import type { UploadedFile } from '@core/form/useFormSubmit';

export function useDxFormRuntime(detailFields?: Field[]) {
  const formRef = useRef<FormRef | null>(null);
  const gridRef = useRef<DataGridRef | null>(null);
  const uploadedFiles = useRef<UploadedFile[]>([]);
  const isEdit = useRef<boolean>(false);
  const dataSource = useRef<DataSource>(
    new DataSource({
      store: new ArrayStore({
        key: detailFields?.find((field) => field.isIdentity)?.name ?? '',
        data: [],
      }),
    }),
  );

  const getDetailIdField = () => detailFields?.find((field) => field.isIdentity)?.name ?? '';

  const getFieldValue = (field: string) => {
    return formRef.current?.instance().getEditor(field)?.option('value');
  };

  return { formRef, gridRef, uploadedFiles, isEdit, dataSource, getDetailIdField, getFieldValue };
}
