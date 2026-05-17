import { FormRef } from 'devextreme-react/form';
import { Field } from '@core/field/Field';
import { forwardRef, useEffect, useImperativeHandle, useRef } from 'react';
import { FORM_ERRORS_EVENT, httpClient } from '@shared/api/HttpClient';
import { useFormSubmit } from '@core/form/useFormSubmit';
import { DataGridRef } from 'devextreme-react/data-grid';
import ArrayStore from 'devextreme/data/array_store';
import DataSource from 'devextreme/data/data_source';
import { useEvents } from '@core/event/EventHook';
import { buildField } from './FormUtils';
import { useCoreTranslation } from '@core/i18n/useCoreTranslation';
import { buildEmptyRow, normalizeFormData } from '@core/form/DxFormDataTransform';
import { DxFormDetailGrid } from './DxFormDetailGrid';
import { useDxFormApi } from './useDxFormApi';
import type { FormHandle } from '@core/form/FormHandle';
import { FORM_EVENTS } from '@core/form/FormEvents';
import type { FormViewOptions } from '@core/form/FormViewOptions';
import type { UploadedFile } from '@core/form/useFormSubmit';
import { useFormComputedValues } from '@core/form/useFormComputedValues';
import { useFormValidation } from '@core/form/useFormValidation';
import { useFormFieldDataChanged } from '@core/form/useFormFieldDataChanged';
import { useFormSectionOptions } from '@core/form/useFormSectionOptions';
import toast from '@shared/lib/SharedUtils';
import { DxFormLayoutRenderer } from './DxFormLayoutRenderer';

type FormRecord = Record<string, unknown>;
type FormError = { propertyPath: string; message: string };
type EventRowPayload = FormRecord & { row?: FormRecord };
type PendingAddPayload = { payload: EventRowPayload | undefined };

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
  const pendingAddPayload = useRef<PendingAddPayload | null>(null);
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

  const getIdField = () => {
    return options.fields.find((field) => field.isIdentity)?.name ?? '';
  };

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

  const getData = (rawData: FormRecord) => normalizeFormData(rawData, options.fields);

  const applyAddPayload = (payload: EventRowPayload | undefined) => {
    const form = formRef.current?.instance();
    if (!form) return false;

    uploadedFiles.current = [];
    const store = data.current?.store() as ArrayStore;
    store.clear();
    data.current?.reload();
    if (payload) {
      const row = getData(payload);
      form.option('formData', row);
      options.onFieldDataChanged?.(row);
      if (Array.isArray(payload[detailPropertyName])) {
        setDetailData(payload[detailPropertyName] as FormRecord[]);
      }
    } else {
      const row = buildEmptyRow(options.fields);
      form.option('formData', row);
      options.onFieldDataChanged?.(row);
    }
    isEdit.current = false;
    return true;
  };

  const flushPendingAddPayload = () => {
    const pending = pendingAddPayload.current;
    if (!pending) return;
    if (applyAddPayload(pending.payload)) {
      pendingAddPayload.current = null;
      return;
    }
    window.setTimeout(flushPendingAddPayload, 0);
  };

  const setupObservers = () => {
    on(FORM_ERRORS_EVENT, (errors: FormError[]) => {
      toast(t('form.validationError'), 'error');
      errors.forEach((error) => {
        setError(error.propertyPath, error.message);
      });
    });

    on<boolean>(FORM_EVENTS.LOADING, (loading) => {
      if (loading) {
        gridRef.current?.instance().option('noDataText', '');
        gridRef.current?.instance().beginCustomLoading(t('form.loading'));
      } else {
        gridRef.current?.instance().endCustomLoading();
        gridRef.current?.instance().option('noDataText', detailNoDataText);
      }
    });

    if (options.events?.ADD)
      on(options.events.ADD, (payload: EventRowPayload | undefined) => {
        if (applyAddPayload(payload)) return;
        pendingAddPayload.current = { payload };
        window.setTimeout(flushPendingAddPayload, 0);
      });

    if (options.events?.EDIT)
      on(options.events.EDIT, (payload: { row: FormRecord }) => {
        const row = getData(payload.row);
        formRef.current?.instance().option('formData', row);
        options.onFieldDataChanged?.(row);
        uploadedFiles.current = [];
        isEdit.current = true;
        const detailId = row[getIdField()];
        const detailUrl =
          typeof detailId === 'string' || typeof detailId === 'number'
            ? options.detailUrl?.replace('{id}', String(detailId))
            : undefined;
        if (!detailUrl) return;
        setDetailData([]);
        emit(FORM_EVENTS.LOADING, true);
        httpClient
          .get<FormRecord[]>(detailUrl)
          .then((response) => {
            setDetailData(response.data);
          })
          .finally(() => emit(FORM_EVENTS.LOADING, false));
      });

    if (options.events?.DELETE)
      on(options.events.DELETE, (payload: { row: FormRecord }) => handleDelete(payload));

    if (options.events?.SAVE) on(options.events.SAVE, () => handleSave());
  };

  const setupObserversRef = useRef(setupObservers);
  // eslint-disable-next-line react-hooks/refs -- intentional stale-closure fix: keep ref current with latest closure
  setupObserversRef.current = setupObservers;

  useFormSectionOptions(options.formLayout, formRef);

  useEffect(() => {
    setupObserversRef.current();
    window.setTimeout(flushPendingAddPayload, 0);
  }, []);

  useFormComputedValues({ computedFieldNames, computedValues, formRef, onFieldDataChanged });

  const api = useDxFormApi({ formRef, gridRef, isEdit }, options.fields);
  // Destructure methods used by internal observers
  const { setDetailData, setError } = api;

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
