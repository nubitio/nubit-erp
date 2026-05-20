import { DateTime } from 'luxon';
import { getCoreTimezone } from '../config/CoreConfig';
import { Field } from '../field/Field';
import { FieldType } from '../field/FieldType';
import type { UploadedFile } from './UploadedFile';
import type { FormDataRecord } from './FormDataSnapshot';

export interface SerializeFieldsContext {
  /** Files uploaded during the current form session. */
  uploadedFiles: UploadedFile[];
  /** Retrieves a field value imperatively (used for FILE fields in multipart mode). */
  getFieldValue?: (field: string) => unknown;
  /** Submission format; affects how FILE fields are handled. */
  format?: 'json' | 'multipart';
}

function getIRI(url: string, value: string): string {
  if (value.startsWith('/')) {
    return value;
  }

  return `${url}/${value}`;
}

/**
 * Pure serialization of form data before HTTP submission.
 *
 * Transforms raw form field values into the shape expected by the API:
 * - Applies uploaded file IRIs
 * - Evaluates computed fields
 * - Resolves ENTITY fields to Hydra IRIs
 * - Formats DATE fields as UTC ISO-8601 (from Lima timezone)
 * - Coerces numeric fields
 * - Strips NONE/FILE fields as appropriate
 *
 * This function is extracted from useFormSubmit for testability.
 */
export function serializeFormFields(
  rawData: FormDataRecord,
  fields: Field[],
  ctx: SerializeFieldsContext,
): FormDataRecord {
  const formData = { ...rawData };

  // Apply uploaded file IRIs
  ctx.uploadedFiles.forEach((file) => {
    formData[file.name] = file.iri;
  });

  // Apply computed fields before transport-specific serialization so hidden
  // derived values are still submitted even when they are not rendered in the form.
  fields.forEach((field) => {
    if (!field.computed) return;
    const computedValue = field.computed(formData);
    if (computedValue !== undefined) {
      formData[field.name] = computedValue;
    }
  });

  fields.forEach((field) => {
    if (field.type === FieldType.ENTITY || field.type === FieldType.TAGS) {
      serializeEntityField(formData, field);
      return;
    }

    if (field.type === FieldType.FILE) {
      serializeFileField(formData, field, ctx);
      return;
    }

    if (field.type === FieldType.NONE) {
      if (!field.isIdentity) {
        delete formData[field.name];
      }
      return;
    }

    if (field.type === FieldType.DATE) {
      serializeDateField(formData, field);
      return;
    }

    if (field.type === FieldType.TEXT && field.valueType === 'number') {
      formData[field.name] = Number(formData[field.name]);
    }

    if (field.type === FieldType.NUMBER || field.type === FieldType.CURRENCY) {
      serializeNumericField(formData, field);
    }
  });

  return formData;
}

function serializeEntityField(formData: FormDataRecord, field: Field): void {
  const val = formData[field.name];
  const type = typeof val;

  // Sentinel -999 = "no selection" placeholder used by dropdown editors
  if (val === null || val === undefined || val === '' || val === -999) {
    delete formData[field.name];
    return;
  }

  if (type === 'object' && val !== null && !field.multiple) {
    const entityValue = val as FormDataRecord;
    const atId = entityValue['@id'];
    if (typeof atId === 'string') {
      formData[field.name] = getIRI(field.url ?? '', atId);
    } else {
      // When the API returns plain JSON (no @id), fall back to the numeric/string
      // `id` property before trying the configured valueField.  This covers the
      // common case where the grid loads data without JSON-LD headers and nested
      // objects arrive as { id: 5, name: "..." } instead of { "@id": "/api/…/5" }.
      const idValue = entityValue['id'];
      const resolvedId =
        idValue !== undefined && idValue !== null
          ? String(idValue)
          : String(entityValue[field.valueField]);
      formData[field.name] = getIRI(field.url ?? '', resolvedId);
    }
  }

  if (type === 'string' || type === 'number') {
    formData[field.name] = getIRI(field.url ?? '', val!.toString());
  }

  if (field.multiple) {
    if (Array.isArray(val)) {
      formData[field.name] = val.map((item: unknown) => {
        if (typeof item === 'object') {
          const record = item as FormDataRecord;
          const atId = record['@id'];
          if (typeof atId === 'string') return getIRI(field.url ?? '', atId);
          const idValue = record['id'];
          const resolvedId =
            idValue !== undefined && idValue !== null
              ? String(idValue)
              : String(record[field.valueField]);
          return getIRI(field.url ?? '', resolvedId);
        }
        return getIRI(field.url ?? '', String(item));
      });
    } else {
      formData[field.name] = [getIRI(field.url ?? '', val!.toString())];
    }
  }
}

function serializeFileField(formData: FormDataRecord, field: Field, ctx: SerializeFieldsContext): void {
  if (ctx.format === 'multipart' && ctx.getFieldValue) {
    const files = ctx.getFieldValue(field.name) as File[] | FileList | null | undefined;
    if (files && files.length > 0) {
      formData[field.name] = files[0];
    } else {
      delete formData[field.name];
    }
    return;
  }
  if (Array.isArray(formData[field.name])) {
    delete formData[field.name];
  }
}

function serializeDateField(formData: FormDataRecord, field: Field): void {
  const val = formData[field.name];
  if (val === null || val === undefined) {
    delete formData[field.name];
    return;
  }
  const date = new Date(val as string | number | Date);
  formData[field.name] = DateTime.fromJSDate(date, { zone: getCoreTimezone() }).toUTC().toISO();
}

function serializeNumericField(formData: FormDataRecord, field: Field): void {
  // Skip numeric coercion for the identity (primary key) field.
  // UUID / string keys must reach the URL builder as-is (BUG-011).
  if (field.isIdentity) return;

  if (field.sendAsString) {
    const val = formData[field.name];
    formData[field.name] = val != null ? Number(val).toFixed(field.precision || 2) : null;
  } else {
    formData[field.name] = Number(formData[field.name]);
  }
}

/**
 * Serializes detail grid rows (entity IRIs, numeric coercion, identity cleanup).
 * Pure function — operates on an array of row records.
 */
export function serializeDetailRows(
  rows: FormDataRecord[],
  detailFields: Field[],
  detailIdField: string,
  isEditMode: boolean,
): FormDataRecord[] {
  const details = JSON.parse(JSON.stringify(rows)) as FormDataRecord[];

  detailFields.forEach((field) => {
    if (field.type === FieldType.ENTITY) {
      details.forEach((detail) => {
        const currentValue = detail[field.name];
        if (currentValue === null || currentValue === undefined || currentValue === '' || currentValue === -999) {
          delete detail[field.name];
          return;
        }

        if (typeof currentValue === 'object') {
          const entityValue = currentValue as FormDataRecord;
          const atId = entityValue['@id'];
          if (typeof atId === 'string') {
            detail[field.name] = getIRI(field.url ?? '', atId);
          } else {
            const idValue = entityValue['id'];
            const resolvedId =
              idValue !== undefined && idValue !== null
                ? String(idValue)
                : String(entityValue[field.valueField]);
            detail[field.name] = getIRI(field.url ?? '', resolvedId);
          }
          return;
        }

        if (typeof currentValue === 'string' || typeof currentValue === 'number') {
          detail[field.name] = getIRI(field.url ?? '', String(currentValue));
        }
      });
    }

    if (field.type === FieldType.NUMBER || field.type === FieldType.CURRENCY) {
      details.forEach((detail) => {
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
  details.forEach((detail) => {
    if (!isEditMode) {
      delete detail[detailIdField];
    } else if (typeof detail[detailIdField] === 'string') {
      delete detail[detailIdField];
    }
  });

  return details;
}
