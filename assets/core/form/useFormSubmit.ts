import { DateTime } from 'luxon';
import { MutableRefObject } from 'react';
import { DataGridRef } from 'devextreme-react/data-grid';
import { FormRef } from 'devextreme-react/form';
import ArrayStore from 'devextreme/data/array_store';
import { confirm } from 'devextreme/ui/dialog';
import { httpClient } from '../../shared/api/HttpClient';
import { getIRI } from '../../shared/lib/SharedUtils';
import { LIMA_TZ } from '../../shared/lib/DateUtils';
import { Field } from '../field/Field';
import { FieldType } from '../field/FieldType';
import { FormEventNames } from '../event/EventTypes';

export type UploadedFile = {
  name: string;
  iri: string;
};

export interface UseFormSubmitOptions {
  url: string;
  detailUrl?: string;
  fields: Field[];
  detailFields?: Field[];
  detailPropertyName: string;
  format?: 'json' | 'multipart';
  events?: FormEventNames;
  onSaveSuccess?: (response: any) => void;
  onSaveError?: (error?: any) => void;
  onDeleteSuccess?: (response: any) => void;
  onDeleteError?: (error?: any) => void;
  onLoadingChange?: (loading: boolean) => void;
}

export interface UseFormSubmitRefs {
  formRef: MutableRefObject<FormRef | null>;
  gridRef: MutableRefObject<DataGridRef | null>;
  uploadedFiles: MutableRefObject<UploadedFile[]>;
  isEdit: MutableRefObject<boolean>;
}

export type EmitFn = <T>(name: string, payload?: T) => void;

/**
 * Returns helpers for form serialization and HTTP submit/delete operations.
 * Extracted from DxForm to enable unit testing without mounting the component.
 */
export const useFormSubmit = (
  options: UseFormSubmitOptions,
  refs: UseFormSubmitRefs,
  emit: EmitFn,
  getFieldValueFn: (field: string) => any,
  validateFn: () => boolean,
) => {
  const { formRef, gridRef, uploadedFiles, isEdit } = refs;

  const getIdField = () => options.fields.find((f) => f.isIdentity)?.name ?? '';

  const getDetailIdField = () => options.detailFields?.find((f) => f.isIdentity)?.name ?? '';

  /** Build multipart FormData from a plain object */
  const buildMultipartFormData = (data: Record<string, any>): FormData => {
    const form = new FormData();
    for (const key in data) {
      form.append(key, data[key]);
    }
    return form;
  };

  /** Serialize main form fields for submission */
  const serializeFields = (rawData: Record<string, any>): Record<string, any> => {
    const formData = { ...rawData };

    // Apply uploaded file IRIs
    uploadedFiles.current.forEach((file) => {
      formData[file.name] = file.iri;
    });

    options.fields.forEach((field: Field) => {
      if (field.type === FieldType.ENTITY || field.type === FieldType.TAGS) {
        const val = formData[field.name];
        const type = typeof val;
        // Sentinel -999 = "no selection" placeholder used by dropdown editors
        if (val === null || val === undefined || val === '' || val === -999) {
          delete formData[field.name];
          return;
        }
        if (type === 'object' && val !== null && !field.multiple) {
          // Prefer the JSON-LD IRI (@id) when the value is a full Hydra entity object
          // e.g. { "@id": "/api/categories/uuid", "id": 1, "name": "Root" }
          // getIRI detects the "/" prefix and returns the IRI unchanged (BUG-014).
          const atId: unknown = val['@id'];
          if (typeof atId === 'string') {
            formData[field.name] = getIRI(field.url ?? '', atId);
          } else {
            formData[field.name] = getIRI(field.url ?? '', val[field.valueField].toString());
          }
        }
        if (type === 'string' || type === 'number') {
          formData[field.name] = getIRI(field.url ?? '', val.toString());
        }
        if (field.multiple) {
          if (Array.isArray(val)) {
            formData[field.name] = val.map((item: any) => {
              if (typeof item === 'object') {
                // Prefer @id for full Hydra objects (BUG-014)
                const atId: unknown = item['@id'];
                return typeof atId === 'string'
                  ? getIRI(field.url ?? '', atId)
                  : getIRI(field.url ?? '', item[field.valueField].toString());
              }
              return getIRI(field.url ?? '', item.toString());
            });
          } else {
            formData[field.name] = [getIRI(field.url ?? '', val.toString())];
          }
        }
        return;
      }

      if (field.type === FieldType.FILE) {
        if (options.format === 'multipart') {
          const files = getFieldValueFn(field.name);
          if (files && files.length > 0) {
            formData[field.name] = files[0];
          } else {
            delete formData[field.name];
          }
          return;
        }
        if (Array.isArray(formData[field.name])) {
          delete formData[field.name];
          return;
        }
      }

      if (field.type === FieldType.NONE) {
        if (!field.isIdentity) {
          delete formData[field.name];
          return;
        }
      }

      if (field.type === FieldType.DATE) {
        const val = formData[field.name];
        if (val === null || val === undefined) {
          delete formData[field.name];
          return;
        }
        const date = new Date(val);
        // Interpret the picked date as Lima local time, then convert to UTC ISO-8601.
        // This is correct regardless of the browser's own timezone setting.
        formData[field.name] = DateTime.fromJSDate(date, { zone: LIMA_TZ }).toUTC().toISO();
      }

      if (field.type === FieldType.TEXT && field.valueType === 'number') {
        formData[field.name] = Number(formData[field.name]);
      }

      if (field.type === FieldType.NUMBER || field.type === FieldType.CURRENCY) {
        // Skip numeric coercion for the identity (primary key) field.
        // UUID / string keys must reach the URL builder as-is — coercing them
        // with Number() produces NaN and results in PATCH /api/resource/NaN.
        // (BUG-011)
        if (field.isIdentity) return;
        if (field.sendAsString) {
          const val = formData[field.name];
          formData[field.name] = val != null ? Number(val).toFixed(field.precision || 2) : null;
        } else {
          formData[field.name] = Number(formData[field.name]);
        }
      }
    });

    return formData;
  };

  /** Append serialized detail grid rows to formData */
  const appendDetailRows = (formData: Record<string, any>): void => {
    if (!options.detailFields) return;

    const items = gridRef.current?.instance().getDataSource().items() ?? [];
    formData[options.detailPropertyName] = JSON.parse(JSON.stringify(items));

    const detailIdField = getDetailIdField();

    options.detailFields.forEach((field: Field) => {
      if (field.type === FieldType.ENTITY) {
        formData[options.detailPropertyName].forEach((detail: any) => {
          const type = typeof (detail[field.name] ?? '');
          if (type === 'object') {
            // Prefer the JSON-LD IRI (@id) over the raw id when available (BUG-014)
            const atId: unknown = detail[field.name]['@id'];
            if (typeof atId === 'string') {
              detail[field.name] = getIRI(field.url ?? '', atId);
            } else {
              detail[field.name] = getIRI(
                field.url ?? '',
                detail[field.name][field.valueField].toString(),
              );
            }
          }
        });
      }

      if (field.type === FieldType.NUMBER || field.type === FieldType.CURRENCY) {
        formData[options.detailPropertyName].forEach((detail: any) => {
          if (field.sendAsString) {
            const val = detail[field.name];
            detail[field.name] = val != null ? Number(val).toFixed(field.precision || 2) : null;
          } else {
            detail[field.name] = Number(detail[field.name]);
          }
        });
      }
    });

    // Remove identity field for new rows (string IDs are temp keys from ArrayStore)
    formData[options.detailPropertyName].forEach((detail: any) => {
      if (!isEdit.current) {
        delete detail[detailIdField];
      } else if (typeof detail[detailIdField] === 'string') {
        delete detail[detailIdField];
      }
    });
  };

  /** Handle the SAVE event: validate → serialize → POST or PATCH */
  const handleSave = (): void => {
    if (!validateFn()) return;

    const { LOADING: loadingEvent, SUCCESS: successEvent } = options.events ?? {};
    if (loadingEvent) emit(loadingEvent, true);
    options.onLoadingChange?.(true);

    const rawData = formRef.current?.instance().option('formData') ?? {};
    let formData = serializeFields(rawData);
    appendDetailRows(formData);

    const id = formData[getIdField()];

    let payload: Record<string, any> | FormData = formData;
    if (options.format === 'multipart') {
      payload = buildMultipartFormData(formData);
    }

    if (isEdit.current) {
      httpClient
        .patch(`${options.url}/${id}`, payload)
        .subscribe({
          next: (response: any) => {
            if (successEvent) emit(successEvent, response);
            options.onSaveSuccess?.(response);
          },
          error: (err: any) => {
            if (loadingEvent) emit(loadingEvent, false);
            options.onLoadingChange?.(false);
            options.onSaveError?.(err);
          },
        })
        .add(() => {
          if (loadingEvent) emit(loadingEvent, false);
          options.onLoadingChange?.(false);
        });
    } else {
      httpClient
        .post(options.url, payload)
        .subscribe({
          next: (response: any) => {
            if (successEvent) emit(successEvent, response);
            options.onSaveSuccess?.(response);
          },
          error: (err: any) => {
            if (loadingEvent) emit(loadingEvent, false);
            options.onLoadingChange?.(false);
            options.onSaveError?.(err);
          },
        })
        .add(() => {
          if (loadingEvent) emit(loadingEvent, false);
          options.onLoadingChange?.(false);
        });
    }
  };

  /** Handle the DELETE event: confirm dialog → DELETE HTTP */
  const handleDelete = (payload: any): void => {
    const { LOADING: loadingEvent, SUCCESS: successEvent } = options.events ?? {};
    const result = confirm('¿Está seguro de eliminar el registro?', 'Confirmar eliminación');
    result.then((dialogResult) => {
      if (!dialogResult) return;
      if (loadingEvent) emit(loadingEvent, true);
      options.onLoadingChange?.(true);
      httpClient
        .delete(`${options.url}/${payload.row[getIdField()]}`)
        .subscribe({
          next: (response: any) => {
            if (successEvent) emit(successEvent, response);
            options.onDeleteSuccess?.(response);
          },
          error: (err: any) => {
            if (loadingEvent) emit(loadingEvent, false);
            options.onLoadingChange?.(false);
            options.onDeleteError?.(err);
          },
        })
        .add(() => {
          if (loadingEvent) emit(loadingEvent, false);
          options.onLoadingChange?.(false);
        });
    });
  };

  return { handleSave, handleDelete, serializeFields, appendDetailRows };
};
