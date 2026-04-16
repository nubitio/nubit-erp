import { Field } from '../field/Field';
import { FieldType } from '../field/FieldType';
import { DateUtils } from '../../shared/lib/DateUtils';

type FormRecord = Record<string, unknown>;

function upsertPrependData(field: Field, item: FormRecord): void {
  const existingLoadOption = field.loadOptions.find((option) => Array.isArray(option.prependData));

  if (existingLoadOption) {
    const prependData = existingLoadOption.prependData as FormRecord[];
    const nextId = item[field.valueField];
    const alreadyPresent = prependData.some((candidate) => candidate[field.valueField] === nextId);
    if (!alreadyPresent) {
      prependData.unshift(item);
    }
    return;
  }

  field.loadOptions = [{ prependData: [item] }, ...field.loadOptions];
}

/**
 * Builds an empty row from field definitions, applying default values.
 */
export function buildEmptyRow(fields: Field[]): FormRecord {
  const row: FormRecord = {};
  fields.forEach((field) => {
    if (field.defaultValue !== undefined) {
      row[field.name] =
        field.defaultValue instanceof Function ? field.defaultValue() : field.defaultValue;
    } else {
      row[field.name] = undefined;
    }
  });
  return row;
}

/**
 * Normalizes raw API data into the shape expected by DevExtreme Form editors.
 *
 * - FILE fields are stripped (uploaded separately)
 * - PASSWORD fields are blanked
 * - DATE fields are parsed into JS Date objects
 * - ENTITY fields are normalized to `{ [valueField]: key }` for SelectBox matching
 */
export function normalizeFormData(data: FormRecord, fields: Field[]): FormRecord {
  const row = { ...data };
  fields.forEach((field) => {
    if (field.type === FieldType.FILE) {
      delete row[field.name];
    }
    if (field.type === FieldType.PASSWORD) {
      row[field.name] = '';
    }
    if (field.type === FieldType.DATE) {
      const rawValue = row[field.name];
      const type = typeof rawValue;
      if (type === 'string') {
        row[field.name] = DateUtils.dateParser(rawValue as string);
      } else if (type === 'object') {
        row[field.name] = new Date(rawValue as string | number | Date);
      } else {
        row[field.name] = null;
      }
    }
    if (field.type === FieldType.ENTITY) {
      normalizeEntityField(row, field);
    }
  });

  return row;
}

function normalizeEntityField(row: FormRecord, field: Field): void {
  const rawValue = row[field.name];
  const type = typeof rawValue;
  if (type === 'string') {
    // If valueField is '_iri', preserve the full IRI; otherwise extract the last segment.
    const value =
      field.valueField === '_iri' ? rawValue : (rawValue as string).split('/').pop();
    row[field.name] = { [field.valueField]: value };
  }
  if (type === 'object' && row[field.name] !== null) {
    const entityValue = row[field.name] as Record<string, unknown>;
    upsertPrependData(field, entityValue);
    const atId: unknown = entityValue['@id'];
    if (typeof atId === 'string') {
      // When valueField is '_iri', keep the full IRI so the SelectBox can match
      // against datasource items (which also have '_iri' as the full IRI).
      const value = field.valueField === '_iri' ? atId : (atId.split('/').pop() ?? atId);
      row[field.name] = { [field.valueField]: value };
    } else {
      const directValue: unknown = (row[field.name] as Record<string, unknown>)[field.valueField];
      if (directValue !== undefined && directValue !== null) {
        row[field.name] = { [field.valueField]: directValue };
      }
    }
  }
  if (row[field.name] === null || row[field.name] === undefined) {
    row[field.name] = field.loadOptions[0]?.prependData[0] ?? null;
  }
}
