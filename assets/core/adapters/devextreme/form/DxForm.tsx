import { forwardRef, useImperativeHandle } from 'react';
import { useFormSubmit } from '@core/form/useFormSubmit';
import { useEvents } from '@core/event/EventHook';
import { useCoreTranslation } from '@core/i18n/useCoreTranslation';
import { useDxFormApi } from './useDxFormApi';
import type { FormHandle } from '@core/form/FormHandle';
import type { FormViewOptions } from '@core/form/FormViewOptions';
import { useFormComputedValues } from '@core/form/useFormComputedValues';
import { useFormValidation } from '@core/form/useFormValidation';
import { useFormFieldDataChanged } from '@core/form/useFormFieldDataChanged';
import { useFormSectionOptions } from '@core/form/useFormSectionOptions';
import { buildFormLayoutModel } from '@core/form/FormLayoutModel';
import { DxFormLayoutRenderer } from './DxFormLayoutRenderer';
import { useDxFormObservers } from './useDxFormObservers';
import { useDxFormRuntime } from './useDxFormRuntime';
import { useDxFormFieldRenderer } from './useDxFormFieldRenderer';
import { useDxFormSubmitAccessors } from './useDxFormSubmitAccessors';
import { useDxFormValidationAccessors } from './useDxFormValidationAccessors';
import { useDxFormComputedValuesAccessors } from './useDxFormComputedValuesAccessors';
import { useDxFormSectionOptionsAccessors } from './useDxFormSectionOptionsAccessors';
import { useDxFormFieldDataChanged } from './useDxFormFieldDataChanged';

export type DxFormOptions = FormViewOptions;

export type DxFormEvents = FormHandle;

export const DxForm = forwardRef<DxFormEvents, DxFormOptions>((options, ref) => {
  const { t } = useCoreTranslation();
  const { computedValues, onFieldDataChanged } = options;
  const computedFieldNames = options.fields
    .filter((field) => field.computed !== undefined)
    .map((field) => field.name);
  const { formRef, gridRef, uploadedFiles, isEdit, dataSource, getDetailIdField, getFieldValue } =
    useDxFormRuntime(options.detailFields);
  const [on, emit] = useEvents();
  const detailPropertyName = options.detailPropertyName ?? 'items';
  const detailNoDataText = options.requiredDetail
    ? t('form.detailRequired')
    : t('form.detailEmpty');
  const formLayoutModel = buildFormLayoutModel(
    options.fields,
    options.formLayout,
    t('form.groupOther'),
  );

  const validationAccessors = useDxFormValidationAccessors({ formRef, gridRef });
  const submitAccessors = useDxFormSubmitAccessors({ formRef, gridRef, isEdit, uploadedFiles });
  const computedValuesAccessors = useDxFormComputedValuesAccessors(formRef);
  const sectionOptionsAccessors = useDxFormSectionOptionsAccessors(formRef);

  const validate = useFormValidation({
    accessors: validationAccessors,
    detailFields: options.detailFields,
    detailNoDataText,
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
    submitAccessors,
    emit,
    validate,
  );

  const renderFields = useDxFormFieldRenderer(options.editable, uploadedFiles, formRef);

  useFormSectionOptions(formLayoutModel, sectionOptionsAccessors);

  useFormComputedValues({
    accessors: computedValuesAccessors,
    computedFieldNames,
    computedValues,
    onFieldDataChanged,
  });

  const api = useDxFormApi({ formRef, gridRef, isEdit }, options.fields);
  // Destructure methods used by internal observers
  const { setDetailData, setError } = api;

  useDxFormObservers({
    dataSource,
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

  const handleFormDataChanged = useFormFieldDataChanged(onFieldDataChanged);
  const fieldChangeHandler = useDxFormFieldDataChanged(handleFormDataChanged);

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
      dataSource={dataSource.current}
      detailFields={options.detailFields}
      detailIdField={getDetailIdField()}
      detailNoDataText={detailNoDataText}
      emit={emit}
      fieldChangeHandler={fieldChangeHandler}
      fields={options.fields}
      formLayoutModel={formLayoutModel}
      formRef={formRef}
      gridRef={gridRef}
      renderFields={renderFields}
    />
  );
});

DxForm.displayName = 'DxForm';
