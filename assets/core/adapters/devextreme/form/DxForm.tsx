import { FormRef } from 'devextreme-react/form';
import { Field } from '@core/field/Field';
import { forwardRef, useImperativeHandle, useRef } from 'react';
import { useFormSubmit } from '@core/form/useFormSubmit';
import { DataGridRef } from 'devextreme-react/data-grid';
import ArrayStore from 'devextreme/data/array_store';
import DataSource from 'devextreme/data/data_source';
import { useEvents } from '@core/event/EventHook';
import { buildField } from './FormUtils';
import { useCoreTranslation } from '@core/i18n/useCoreTranslation';
import { useDxFormApi } from './useDxFormApi';
import type { FormHandle } from '@core/form/FormHandle';
import type { FormViewOptions } from '@core/form/FormViewOptions';
import type { UploadedFile } from '@core/form/useFormSubmit';
import { useFormComputedValues } from '@core/form/useFormComputedValues';
import { useFormValidation } from '@core/form/useFormValidation';
import { useFormFieldDataChanged } from '@core/form/useFormFieldDataChanged';
import { useFormSectionOptions } from '@core/form/useFormSectionOptions';
import { DxFormLayoutRenderer } from './DxFormLayoutRenderer';
import { useDxFormObservers } from './useDxFormObservers';

type FormRecord = Record<string, unknown>;

export type DxFormOptions = FormViewOptions;

export type DxFormEvents = FormHandle;

export const DxForm = forwardRef<DxFormEvents, DxFormOptions>((options, ref) => {
  const { t } = useCoreTranslation();
  const { computedValues, onFieldDataChanged } = options;
  const computedFieldNames = options.fields
    .filter((field) => field.computed !== undefined)
    .map((field) => field.name);
  const formRef = useRef<FormRef | null>(null);
  const gridRef = useRef<DataGridRef | null>(null);
  const uploadedFiles = useRef<UploadedFile[]>([]);
  const isEdit = useRef<boolean>(false);
  const data = useRef<DataSource>(
    new DataSource({
      store: new ArrayStore({
        key: options.detailFields?.find((field) => field.isIdentity)?.name ?? '',
        data: [],
      }),
    }),
  );
  const [on, emit] = useEvents();
  const detailPropertyName = options.detailPropertyName ?? 'items';
  const detailNoDataText = options.requiredDetail
    ? t('form.detailRequired')
    : t('form.detailEmpty');

  const getDetailIdField = () => {
    return options.detailFields?.find((field) => field.isIdentity)?.name ?? '';
  };

  const getFieldValue = (field: string) => {
    return formRef.current?.instance().getEditor(field)?.option('value');
  };

  const validate = useFormValidation({
    detailFields: options.detailFields,
    detailNoDataText,
    formRef,
    gridRef,
    requiredDetail: options.requiredDetail,
    validationErrorText: t('form.validationError'),
  });

  const { handleSave, handleDelete } = useFormSubmit(
    {
      url: options.url,
      detailUrl: options.detailUrl,
      fields: options.fields,
      detailFields: options.detailFields,
      detailPropertyName,
      format: options.format,
      events: options.events,
      onSaveSuccess: options.onSaveSuccess,
      onSaveError: options.onSaveError,
      onDeleteSuccess: options.onDeleteSuccess,
      onDeleteError: options.onDeleteError,
      onLoadingChange: options.onLoadingChange,
    },
    { formRef, gridRef, uploadedFiles, isEdit },
    emit,
    getFieldValue,
    validate,
  );

  const buildFields = (fields: Field[]) => {
    return fields.map((field: Field, index) => {
      return buildField(field, index, options.editable ?? true, uploadedFiles, formRef);
    });
  };

  useFormSectionOptions(options.formLayout, formRef);

  useFormComputedValues({ computedFieldNames, computedValues, formRef, onFieldDataChanged });

  const api = useDxFormApi({ formRef, gridRef, isEdit }, options.fields);
  // Destructure methods used by internal observers
  const { setDetailData, setError } = api;

  useDxFormObservers({
    dataSource: data,
    detailNoDataText,
    detailPropertyName,
    detailUrl: options.detailUrl,
    emit,
    events: options.events,
    fields: options.fields,
    formRef,
    gridRef,
    handleDelete,
    handleSave,
    isEdit,
    loadingText: t('form.loading'),
    on,
    onFieldDataChanged: options.onFieldDataChanged,
    setDetailData,
    setError,
    uploadedFiles,
    validationErrorText: t('form.validationError'),
  });

  const fieldChangeHandler = useFormFieldDataChanged(onFieldDataChanged);

  useImperativeHandle(ref, () => ({
    ...api,
    // Override with DxForm-specific validate that includes detail grid validation + toast
    validate,
  }));

  return (
    <DxFormLayoutRenderer
      allowAdding={options.allowAdding}
      allowDeleting={options.allowDeleting}
      allowUpdating={options.allowUpdating}
      className={options.className}
      dataSource={data.current}
      detailFields={options.detailFields}
      detailIdField={getDetailIdField()}
      detailNoDataText={detailNoDataText}
      emit={emit}
      fieldChangeHandler={fieldChangeHandler}
      fields={options.fields}
      formLayout={options.formLayout}
      formRef={formRef}
      gridRef={gridRef}
      groupOtherText={t('form.groupOther')}
      renderFields={buildFields}
    />
  );
});

DxForm.displayName = 'DxForm';
