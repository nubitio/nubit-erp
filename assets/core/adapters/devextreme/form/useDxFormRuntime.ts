import { useRef } from 'react';
import { FormRef } from 'devextreme-react/form';
import { DataGridRef } from 'devextreme-react/data-grid';
import DataSource from 'devextreme/data/data_source';

import type { Field } from '@core/field/Field';
import type { UploadedFile } from '@core/form/UploadedFile';
import { getDxFieldValue } from './DxFormAccessorReads';
import { createDxDetailDataSource } from './DxFormDetailDataSource';

export function useDxFormRuntime(detailFields?: Field[]) {
  const formRef = useRef<FormRef | null>(null);
  const gridRef = useRef<DataGridRef | null>(null);
  const uploadedFiles = useRef<UploadedFile[]>([]);
  const isEdit = useRef<boolean>(false);
  const dataSource = useRef<DataSource>(
    createDxDetailDataSource(detailFields?.find((field) => field.isIdentity)?.name ?? ''),
  );

  const getDetailIdField = () => detailFields?.find((field) => field.isIdentity)?.name ?? '';

  const getFieldValue = (field: string) => {
    return getDxFieldValue(formRef, field);
  };

  return { formRef, gridRef, uploadedFiles, isEdit, dataSource, getDetailIdField, getFieldValue };
}
