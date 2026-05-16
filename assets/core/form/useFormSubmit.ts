import { MutableRefObject } from 'react';
import { DataGridRef } from 'devextreme-react/data-grid';
import { FormRef } from 'devextreme-react/form';
import { confirm } from 'devextreme/ui/dialog';
import { httpClient } from '../../shared/api/HttpClient';
import { Field } from '../field/Field';
import { FormEventNames } from '../event/EventTypes';
import { serializeFormFields, serializeDetailRows } from './serializeFormData';
import { useCoreTranslation } from '../i18n/useCoreTranslation';

export type { UploadedFileEntry as UploadedFile } from './serializeFormData';

type FormRecord = Record<string, unknown>;
type DeletePayload = { row: FormRecord };

export interface UseFormSubmitOptions {
  url: string;
  detailUrl?: string;
  fields: Field[];
  detailFields?: Field[];
  detailPropertyName: string;
  format?: 'json' | 'multipart';
  events?: FormEventNames;
  onSaveSuccess?: (response: unknown) => void;
  onSaveError?: (error?: unknown) => void;
  onDeleteSuccess?: (response: unknown) => void;
  onDeleteError?: (error?: unknown) => void;
  onLoadingChange?: (loading: boolean) => void;
}

export interface UseFormSubmitRefs {
  formRef: MutableRefObject<FormRef | null>;
  gridRef: MutableRefObject<DataGridRef | null>;
  uploadedFiles: MutableRefObject<{ name: string; iri: string }[]>;
  isEdit: MutableRefObject<boolean>;
}

export type EmitFn = <T>(name: string, payload?: T) => void;

/**
 * Returns helpers for form serialization and HTTP submit/delete operations.
 * Delegates pure serialization to `serializeFormFields` / `serializeDetailRows`.
 */
export const useFormSubmit = (
  options: UseFormSubmitOptions,
  refs: UseFormSubmitRefs,
  emit: EmitFn,
  getFieldValueFn: (field: string) => unknown,
  validateFn: () => boolean,
) => {
  const { t } = useCoreTranslation();
  const { formRef, gridRef, uploadedFiles, isEdit } = refs;

  const getIdField = () => options.fields.find((f) => f.isIdentity)?.name ?? '';
  const getDetailIdField = () => options.detailFields?.find((f) => f.isIdentity)?.name ?? '';

  /** Build multipart FormData from a plain object */
  const buildMultipartFormData = (data: FormRecord): FormData => {
    const form = new FormData();
    for (const key in data) {
      const value = data[key];
      if (value != null) {
        form.append(key, value instanceof Blob ? value : String(value));
      }
    }
    return form;
  };

  /** Serialize main form fields for submission */
  const serializeFields = (rawData: FormRecord): FormRecord => {
    return serializeFormFields(rawData, options.fields, {
      uploadedFiles: uploadedFiles.current,
      getFieldValue: getFieldValueFn,
      format: options.format,
    });
  };

  /** Append serialized detail grid rows to formData */
  const appendDetailRows = (formData: FormRecord): void => {
    if (!options.detailFields) return;

    const items = (gridRef.current?.instance().getDataSource().items() ?? []) as FormRecord[];
    formData[options.detailPropertyName] = serializeDetailRows(
      items,
      options.detailFields,
      getDetailIdField(),
      isEdit.current,
    );
  };

  /** Handle the SAVE event: validate → serialize → POST or PATCH */
  const handleSave = (): void => {
    if (!validateFn()) return;

    const { LOADING: loadingEvent, SUCCESS: successEvent } = options.events ?? {};
    if (loadingEvent) emit(loadingEvent, true);
    options.onLoadingChange?.(true);

    const rawData = (formRef.current?.instance().option('formData') ?? {}) as FormRecord;
    const formData = serializeFields(rawData);
    appendDetailRows(formData);

    const id = formData[getIdField()];

    let payload: FormRecord | FormData = formData;
    if (options.format === 'multipart') {
      payload = buildMultipartFormData(formData);
    }

    const finalize = () => {
      if (loadingEvent) emit(loadingEvent, false);
      options.onLoadingChange?.(false);
    };

    const request = isEdit.current
      ? httpClient.patch(`${options.url}/${id}`, payload)
      : httpClient.post(options.url, payload);

    request
      .then((response) => {
        if (successEvent) emit(successEvent, response);
        options.onSaveSuccess?.(response);
      })
      .catch((err: unknown) => {
        options.onSaveError?.(err);
      })
      .finally(finalize);
  };

  /** Handle the DELETE event: confirm dialog → DELETE HTTP */
  const handleDelete = (payload: DeletePayload): void => {
    const { LOADING: loadingEvent, SUCCESS: successEvent } = options.events ?? {};
    const result = confirm(t('dialog.confirmDelete'), t('dialog.confirmDeleteTitle'));
    result.then((dialogResult) => {
      if (!dialogResult) return;
      if (loadingEvent) emit(loadingEvent, true);
      options.onLoadingChange?.(true);

      const finalize = () => {
        if (loadingEvent) emit(loadingEvent, false);
        options.onLoadingChange?.(false);
      };

      httpClient
        .delete(`${options.url}/${payload.row[getIdField()]}`)
        .then((response: unknown) => {
          if (successEvent) emit(successEvent, response);
          options.onDeleteSuccess?.(response);
        })
        .catch((err: unknown) => {
          options.onDeleteError?.(err);
        })
        .finally(finalize);
    });
  };

  return { handleSave, handleDelete, serializeFields, appendDetailRows };
};
