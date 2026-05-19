import './NativeFormView.scss';

import React, {
  forwardRef,
  useCallback,
  useEffect,
  useImperativeHandle,
  useMemo,
  useRef,
  useState,
} from 'react';

import { FORM_ERRORS_EVENT, httpClient } from '@shared/api/HttpClient';
import toast from '@shared/lib/SharedUtils';
import { useEvents } from '@core/event/EventHook';
import { Field } from '@core/field/Field';
import { FieldType } from '@core/field/FieldType';
import { ApiPlatformRemoteDataSource } from '@core/data/ApiPlatformRemoteDataSource';
import type { DataRecord } from '@core/data/DataRecord';
import { useCoreTranslation } from '@core/i18n/useCoreTranslation';
import { FORM_EVENTS } from './FormEvents';
import { buildEmptyRow, normalizeFormData } from './FormDataTransform';
import { buildFormLayoutModel, type FormLayoutGroup, type FormLayoutModel } from './FormLayoutModel';
import type { FormDataRecord } from './FormDataSnapshot';
import type { FormHandle } from './FormHandle';
import type { FormViewOptions } from './FormViewOptions';
import { useFormSubmit } from './useFormSubmit';
import { useFormValidation } from './useFormValidation';
import type { UploadedFile } from './UploadedFile';

type FieldState = { readonly?: boolean; disabled?: boolean; hidden?: boolean; validationEnabled?: boolean };
type FormError = { propertyPath: string; message: string };
type EventRowPayload = FormDataRecord & { row?: FormDataRecord };

function fieldKeyValue(field: Field, item: DataRecord): unknown {
  if (field.valueField === '_iri') return item['_iri'] ?? item['@id'] ?? item['id'];
  return item[field.valueField] ?? item['value'] ?? item['id'] ?? item['@id'];
}

function fieldTextValue(field: Field, item: DataRecord): string {
  const value = item[field.textField] ?? item['text'] ?? item['name'] ?? item['businessName'];
  return value == null ? '' : String(value);
}

function fieldFilters(field: Field): unknown[][] | undefined {
  if (field.filters.length === 0) return undefined;
  return field.filters.map((filter) => [filter.field, filter.operator, filter.value]);
}

function inputValue(value: unknown): string | number | readonly string[] | undefined {
  if (value instanceof Date) return value.toISOString().slice(0, 10);
  if (typeof value === 'string' || typeof value === 'number') return value;
  return value == null ? '' : String(value);
}

function isEmptyValue(value: unknown): boolean {
  return value === null || value === undefined || value === '' || (Array.isArray(value) && value.length === 0);
}

function colSpan(field: Field): React.CSSProperties {
  return { '--native-form-col': String(field.col ?? 12) } as React.CSSProperties;
}

function validateField(field: Field, value: unknown, formData: FormDataRecord): string | null {
  if (field.required && isEmptyValue(value)) return `${field.label} es requerido`;

  for (const rule of field.validators ?? []) {
    if (rule.type === 'required' && isEmptyValue(value)) return rule.options.message ?? `${field.label} es requerido`;
    if (rule.type === 'email' && typeof value === 'string' && value !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
      return rule.options.message ?? 'Correo inválido';
    }
    if (rule.type === 'numeric' && value !== '' && value != null && Number.isNaN(Number(value))) {
      return rule.options.message ?? 'Valor numérico inválido';
    }
    if (rule.type === 'pattern' && typeof value === 'string' && !new RegExp(rule.options.pattern).test(value)) {
      return rule.options.message ?? 'Formato inválido';
    }
    if (rule.type === 'stringLength' && typeof value === 'string') {
      if (rule.options.min !== undefined && value.length < rule.options.min) return rule.options.message ?? 'Texto demasiado corto';
      if (rule.options.max !== undefined && value.length > rule.options.max) return rule.options.message ?? 'Texto demasiado largo';
    }
    if (rule.type === 'range' && value !== '' && value != null) {
      const numericValue = Number(value);
      if (rule.options.min !== undefined && numericValue < rule.options.min) return rule.options.message ?? 'Valor fuera de rango';
      if (rule.options.max !== undefined && numericValue > rule.options.max) return rule.options.message ?? 'Valor fuera de rango';
    }
    if (rule.type === 'compare') {
      const target = rule.options.comparisonTarget();
      const comparison = rule.options.comparisonType ?? '==';
      const valid =
        comparison === '==' ? value === target :
        comparison === '!=' ? value !== target :
        comparison === '>' ? Number(value) > Number(target) :
        comparison === '<' ? Number(value) < Number(target) :
        comparison === '>=' ? Number(value) >= Number(target) :
        Number(value) <= Number(target);
      if (!valid) return rule.options.message ?? 'Valor inválido';
    }
    if (rule.type === 'custom' && !rule.options.validationCallback({ value, data: formData })) {
      return rule.options.message ?? 'Valor inválido';
    }
  }

  return null;
}

function NativeDetailGrid({
  allowAdding = true,
  allowDeleting = true,
  allowUpdating = true,
  detailFields,
  onRowsChange,
  onRowsMutated,
  remoteOptions,
  rows,
}: {
  allowAdding?: boolean;
  allowDeleting?: boolean;
  allowUpdating?: boolean;
  detailFields: Field[];
  onRowsChange: (rows: FormDataRecord[]) => void;
  onRowsMutated: () => void;
  remoteOptions: Record<string, DataRecord[]>;
  rows: FormDataRecord[];
}) {
  const visibleFields = detailFields.filter((field) => field.visible && !field.hidden);

  const updateCell = async (rowIndex: number, field: Field, value: unknown) => {
    let nextRow = { ...rows[rowIndex], [field.name]: value };
    if (field.onChange) {
      const draft = { ...rows[rowIndex] };
      const fieldContext = {
        defaultSetCellValue: (row: FormDataRecord, nextValue: unknown) => {
          row[field.name] = nextValue;
        },
      };
      await (field.onChange as (...args: unknown[]) => void | Promise<void>).call(fieldContext, draft, value, rows[rowIndex]);
      nextRow = { ...nextRow, ...draft };
    }
    const nextRows = rows.map((row, index) => index === rowIndex ? nextRow : row);
    onRowsChange(nextRows);
    onRowsMutated();
  };

  const addRow = () => {
    onRowsChange([...rows, buildEmptyRow(detailFields)]);
    onRowsMutated();
  };

  const removeRow = (rowIndex: number) => {
    onRowsChange(rows.filter((_, index) => index !== rowIndex));
    onRowsMutated();
  };

  const renderDetailControl = (row: FormDataRecord, rowIndex: number, field: Field) => {
    const value = row[field.name];
    if (field.formatter && field.type === FieldType.NONE) {
      return field.formatter({ value, data: row, rowIndex, columnIndex: 0 });
    }
    if (field.type === FieldType.ENTITY || field.type === FieldType.SELECT || field.type === FieldType.ENUM) {
      const items = field.type === FieldType.ENTITY ? (remoteOptions[field.name] ?? field.data ?? []) : (field.data ?? []);
      return (
        <select
          className="native-form__control"
          value={String(value && typeof value === 'object' ? fieldKeyValue(field, value as DataRecord) : value ?? '')}
          disabled={!allowUpdating || field.disabled}
          onChange={(event) => void updateCell(rowIndex, field, event.currentTarget.value === '' ? null : event.currentTarget.value)}
        >
          <option value="">Seleccione...</option>
          {items.map((item) => {
            const itemValue = field.type === FieldType.ENTITY ? fieldKeyValue(field, item) : item['value'];
            const itemText = field.type === FieldType.ENTITY ? fieldTextValue(field, item) : String(item['text'] ?? itemValue ?? '');
            return <option key={String(itemValue)} value={String(itemValue ?? '')}>{itemText}</option>;
          })}
        </select>
      );
    }
    return (
      <input
        className="native-form__control"
        value={inputValue(value)}
        readOnly={!allowUpdating || field.readonly}
        disabled={field.disabled}
        type={field.type === FieldType.NUMBER || field.type === FieldType.CURRENCY ? 'number' : 'text'}
        onChange={(event) => void updateCell(rowIndex, field, event.target.value)}
      />
    );
  };

  return (
    <div className="native-form__detail">
      <div className="native-form__detail-heading">
        <h3 className="native-form__detail-title">Detalle</h3>
        {allowAdding && (
          <button type="button" className="app-icon-button" aria-label="Agregar detalle" onClick={addRow}>
            <i className="ph ph-plus" aria-hidden="true" />
          </button>
        )}
      </div>
      <div className="native-form__detail-table-wrap">
        <table className="native-form__detail-table">
          <thead>
            <tr>
              {visibleFields.map((field) => <th key={field.name}>{field.label}</th>)}
              {allowDeleting && <th />}
            </tr>
          </thead>
          <tbody>
            {rows.length === 0 && (
              <tr>
                <td colSpan={visibleFields.length + (allowDeleting ? 1 : 0)} className="native-form__detail-empty">No items added</td>
              </tr>
            )}
            {rows.map((row, rowIndex) => (
              <tr key={rowIndex}>
                {visibleFields.map((field) => (
                  <td key={field.name}>
                    {renderDetailControl(row, rowIndex, field)}
                  </td>
                ))}
                {allowDeleting && (
                  <td>
                    <button type="button" className="app-icon-button" onClick={() => removeRow(rowIndex)}>
                      <i className="ph ph-trash" aria-hidden="true" />
                    </button>
                  </td>
                )}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {allowAdding && (
        <div className="native-form__detail-actions">
          <button type="button" className="app-button app-button--secondary app-button--sm" onClick={addRow}>
            Agregar detalle
          </button>
        </div>
      )}
    </div>
  );
}

export const NativeFormView = forwardRef<FormHandle, FormViewOptions>((options, ref) => {
  const { t } = useCoreTranslation();
  const [on, emit] = useEvents();
  const isEdit = useRef(false);
  const uploadedFiles = useRef<UploadedFile[]>([]);
  const [formData, setFormData] = useState<FormDataRecord>(() => buildEmptyRow(options.fields));
  const formDataRef = useRef(formData);
  const [detailRows, setDetailRows] = useState<FormDataRecord[]>([]);
  const detailRowsRef = useRef(detailRows);
  const [fieldState, setFieldState] = useState<Record<string, FieldState>>({});
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [remoteOptions, setRemoteOptions] = useState<Record<string, DataRecord[]>>({});
  const [activeTab, setActiveTab] = useState(0);

  const fields = options.fields;
  const onFieldDataChanged = options.onFieldDataChanged;
  const detailPropertyName = options.detailPropertyName ?? 'items';
  const computedFieldNames = useMemo(() => fields.filter((field) => field.computed !== undefined).map((field) => field.name), [fields]);
  const formLayoutModel = useMemo(() => buildFormLayoutModel(fields, options.formLayout, t('form.groupOther')), [fields, options.formLayout, t]);
  const optionFields = useMemo(() => [...fields, ...(options.detailFields ?? [])], [fields, options.detailFields]);

  const setNextFormData = useCallback((nextData: FormDataRecord) => {
    formDataRef.current = nextData;
    setFormData(nextData);
    onFieldDataChanged?.(nextData);
  }, [onFieldDataChanged]);

  const setNextDetailRows = useCallback((nextRows: FormDataRecord[]) => {
    detailRowsRef.current = nextRows;
    setDetailRows(nextRows);
  }, []);

  const notifyDetailRowsMutated = useCallback(() => {
    emit(FORM_EVENTS.ROW_UPDATED, detailRowsRef.current);
  }, [emit]);

  const setFieldValue = useCallback((name: string, value: unknown) => {
    const field = fields.find((candidate) => candidate.name === name);
    const nextData = { ...formDataRef.current, [name]: value };
    formDataRef.current = nextData;
    setFormData(nextData);
    setErrors((current) => ({ ...current, [name]: '' }));
    onFieldDataChanged?.(nextData);
    void (field?.onChange as ((value: unknown) => void | Promise<void>) | undefined)?.(value);
  }, [fields, onFieldDataChanged]);

  useEffect(() => { formDataRef.current = formData; }, [formData]);
  useEffect(() => { detailRowsRef.current = detailRows; }, [detailRows]);

  useEffect(() => {
    optionFields.forEach((field) => {
      if (field.type !== FieldType.ENTITY && field.type !== FieldType.TAGS) return;
      if (!field.url) {
        setRemoteOptions((current) => ({ ...current, [field.name]: field.data ?? [] }));
        return;
      }
      const source = new ApiPlatformRemoteDataSource({
        url: field.url,
        idField: field.valueField,
        byKeyUrl: field.byKeyUrl,
        defaultFilterRules: fieldFilters(field),
        options: field.loadOptions,
        iriMode: field.valueField === '_iri',
      });
      source
        .load({ take: 50 })
        .then((result) => {
          setRemoteOptions((current) => ({ ...current, [field.name]: result.data }));
          if (field.autoSelectIfSingle && result.data.length === 1 && isEmptyValue(formDataRef.current[field.name])) {
            setFieldValue(field.name, fieldKeyValue(field, result.data[0]));
          }
        })
        .catch(() => {
          setRemoteOptions((current) => ({ ...current, [field.name]: field.data ?? [] }));
        });
    });
  }, [optionFields, setFieldValue]);

  useEffect(() => {
    fields.forEach((field) => {
      const optionsForField = remoteOptions[field.name];
      if (!field.autoSelectIfSingle || optionsForField?.length !== 1 || !isEmptyValue(formDataRef.current[field.name])) return;
      setFieldValue(field.name, fieldKeyValue(field, optionsForField[0]));
    });
  }, [fields, formData, remoteOptions, setFieldValue]);

  const validateForm = useCallback(() => {
    const nextErrors: Record<string, string> = {};
    options.fields.forEach((field) => {
      const state = fieldState[field.name];
      if (field.isIdentity || state?.validationEnabled === false || state?.hidden === true || field.hidden || field.visibleOnForm === false) return;
      const error = validateField(field, formDataRef.current[field.name], formDataRef.current);
      if (error) nextErrors[field.name] = error;
    });
    setErrors(nextErrors);
    return Object.keys(nextErrors).length === 0;
  }, [fieldState, options.fields]);

  const validate = useFormValidation({
    accessors: {
      getDetailRowCount: () => detailRowsRef.current.length,
      getDetailRows: () => detailRowsRef.current,
      hasPendingDetailEdits: () => false,
      validateForm,
    },
    detailFields: options.detailFields,
    detailNoDataText: options.requiredDetail ? t('form.detailRequired') : t('form.detailEmpty'),
    requiredDetail: options.requiredDetail,
    validationErrorText: t('form.validationError'),
  });

  const submitAccessors = useMemo(() => ({
    getDetailRows: () => detailRowsRef.current,
    getFieldValue: (field: string) => formDataRef.current[field],
    getFormData: () => formDataRef.current,
    getUploadedFiles: () => uploadedFiles.current,
    isEditMode: () => isEdit.current,
  }), []);

  const { handleSave, handleDelete } = useFormSubmit({
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
  }, submitAccessors, emit, validate);

  const applyAddPayload = useCallback((payload: EventRowPayload | undefined) => {
    uploadedFiles.current = [];
    setNextDetailRows([]);
    const row = payload ? normalizeFormData(payload, options.fields) : buildEmptyRow(options.fields);
    setNextFormData(row);
    if (payload && Array.isArray(payload[detailPropertyName])) {
      setNextDetailRows(payload[detailPropertyName] as FormDataRecord[]);
    }
    isEdit.current = false;
  }, [detailPropertyName, options.fields, setNextDetailRows, setNextFormData]);

  useEffect(() => {
    on<FormError[]>(FORM_ERRORS_EVENT, (formErrors) => {
      toast(t('form.validationError'), 'error');
      setErrors((current) => {
        const next = { ...current };
        formErrors.forEach((error) => { next[error.propertyPath] = error.message; });
        return next;
      });
    });

    if (options.events?.ADD) on(options.events.ADD, (payload: EventRowPayload | undefined) => applyAddPayload(payload));
    if (options.events?.EDIT) {
      on(options.events.EDIT, (payload: { row: FormDataRecord }) => {
        const row = normalizeFormData(payload.row, options.fields);
        uploadedFiles.current = [];
        setNextFormData(row);
        isEdit.current = true;

        const idField = options.fields.find((field) => field.isIdentity)?.name ?? '';
        const detailId = row[idField];
        const detailUrl = typeof detailId === 'string' || typeof detailId === 'number' ? options.detailUrl?.replace('{id}', String(detailId)) : undefined;
        if (!detailUrl) return;

        emit(FORM_EVENTS.LOADING, true);
        httpClient
          .get<FormDataRecord[]>(detailUrl)
          .then((response) => setNextDetailRows(response.data))
          .finally(() => emit(FORM_EVENTS.LOADING, false));
      });
    }
    if (options.events?.DELETE) on(options.events.DELETE, (payload: { row: FormDataRecord }) => handleDelete(payload));
    if (options.events?.SAVE) on(options.events.SAVE, () => handleSave());
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    if (computedFieldNames.length === 0 || !options.computedValues) return;
    let changed = false;
    const nextData = { ...formDataRef.current };
    computedFieldNames.forEach((name) => {
      if (Object.prototype.hasOwnProperty.call(options.computedValues, name) && nextData[name] !== options.computedValues?.[name]) {
        nextData[name] = options.computedValues?.[name];
        changed = true;
      }
    });
    if (changed) setNextFormData(nextData);
  }, [computedFieldNames, options.computedValues, setNextFormData]);

  useImperativeHandle(ref, () => ({
    getFormData: () => formDataRef.current,
    getFieldValue: (name) => formDataRef.current[name],
    setFieldValue,
    getDetailData: () => detailRowsRef.current,
    setDetailData: setNextDetailRows,
    saveDetailData: () => undefined,
    reloadDetailData: () => undefined,
    setValues: (data) => setNextFormData(normalizeFormData(data, options.fields)),
    setReadonly: (field, value) => setFieldState((current) => ({ ...current, [field]: { ...current[field], readonly: value } })),
    setDisabled: (field, value) => setFieldState((current) => ({ ...current, [field]: { ...current[field], disabled: value } })),
    enableValidation: (field) => setFieldState((current) => ({ ...current, [field]: { ...current[field], validationEnabled: true } })),
    disableValidation: (field) => setFieldState((current) => ({ ...current, [field]: { ...current[field], validationEnabled: false } })),
    setError: (field, message) => setErrors((current) => ({ ...current, [field]: message })),
    showField: (field) => setFieldState((current) => ({ ...current, [field]: { ...current[field], hidden: false } })),
    hideField: (field) => setFieldState((current) => ({ ...current, [field]: { ...current[field], hidden: true } })),
    validate,
    setIsEdit: (value) => { isEdit.current = value; },
  }), [options.fields, setFieldValue, setNextDetailRows, setNextFormData, validate]);

  const renderField = (field: Field) => {
    const state = fieldState[field.name] ?? {};
    if (field.isIdentity || !field.visibleOnForm || field.hidden || state.hidden) return null;
    const value = formData[field.name];
    const readOnly = state.readonly ?? field.readonly ?? options.editable === false;
    const disabled = state.disabled ?? field.disabled;
    const error = errors[field.name];
    const commonProps = {
      className: 'native-form__control',
      disabled,
      id: `native-form-${field.name}`,
      name: field.name,
      readOnly,
      required: field.required,
    };

    let control: React.ReactNode;
    if (field.contentRender && typeof field.contentRender === 'function') {
      control = field.contentRender({ data: formData, value });
    } else if (field.type === FieldType.TEXTAREA || field.type === FieldType.HTML) {
      control = <textarea {...commonProps} className="native-form__control native-form__textarea" value={inputValue(value)} onChange={(event) => setFieldValue(field.name, event.target.value)} />;
    } else if (field.type === FieldType.CHECKBOX || field.type === FieldType.SWITCH) {
      control = (
        <label className="native-form__checkbox">
          <input type="checkbox" checked={Boolean(value)} disabled={disabled || readOnly} onChange={(event) => setFieldValue(field.name, event.target.checked)} />
          <span>{field.label}</span>
        </label>
      );
    } else if (field.type === FieldType.RADIO) {
      control = (
        <div className="native-form__radio-list">
          {(field.data ?? []).map((item) => {
            const itemValue = item['value'];
            return <label key={String(itemValue)} className="native-form__radio-item"><input type="radio" checked={value === itemValue} disabled={disabled || readOnly} onChange={() => setFieldValue(field.name, itemValue)} />{String(item['text'] ?? itemValue)}</label>;
          })}
        </div>
      );
    } else if (field.type === FieldType.ENUM || field.type === FieldType.SELECT || field.type === FieldType.ENTITY || field.type === FieldType.TAGS) {
      const items = field.type === FieldType.ENTITY || field.type === FieldType.TAGS ? (remoteOptions[field.name] ?? field.data ?? []) : (field.data ?? []);
      control = (
        <select {...commonProps} multiple={field.multiple || field.type === FieldType.TAGS} value={field.multiple && Array.isArray(value) ? value.map(String) : String(value ?? '')} onChange={(event) => {
          const nextValue = event.currentTarget.multiple ? Array.from(event.currentTarget.selectedOptions).map((option) => option.value) : event.currentTarget.value;
          setFieldValue(field.name, nextValue === '' ? null : nextValue);
        }}>
          <option value="">Seleccione...</option>
          {items.map((item) => {
            const itemValue = field.type === FieldType.ENTITY || field.type === FieldType.TAGS ? fieldKeyValue(field, item) : item['value'];
            const itemText = field.type === FieldType.ENTITY || field.type === FieldType.TAGS ? fieldTextValue(field, item) : String(item['text'] ?? itemValue ?? '');
            return <option key={String(itemValue)} value={String(itemValue ?? '')}>{itemText}</option>;
          })}
        </select>
      );
    } else if (field.type === FieldType.FILE) {
      control = <input {...commonProps} type="file" accept={field.accept ?? undefined} readOnly={undefined} onChange={(event) => {
        setFieldValue(field.name, event.target.files ?? null);
      }} />;
    } else {
      const type = field.type === FieldType.NUMBER || field.type === FieldType.CURRENCY ? 'number' : field.type === FieldType.DATE ? 'date' : field.type === FieldType.DATETIME ? 'datetime-local' : field.type === FieldType.PASSWORD ? 'password' : 'text';
      control = <input {...commonProps} type={type} value={inputValue(value)} onChange={(event) => setFieldValue(field.name, event.target.value)} />;
    }

    return (
      <div key={field.name} className="native-form__field" style={colSpan(field)}>
        {field.type !== FieldType.CHECKBOX && field.type !== FieldType.SWITCH && <label className="native-form__label" htmlFor={`native-form-${field.name}`}>{field.label}</label>}
        {control}
        {field.helpText && <span className="native-form__label">{field.helpText}</span>}
        {error && <span className="native-form__error">{error}</span>}
      </div>
    );
  };

  const renderFields = (fields: Field[]) => <div className="native-form__grid">{fields.map(renderField)}</div>;

  const renderGroup = (group: FormLayoutGroup) => (
    <section key={group.label} className="native-form__section">
      <h3 className="native-form__section-title">{group.label}</h3>
      {renderFields(group.fields)}
    </section>
  );

  const renderLayout = (layout: FormLayoutModel) => {
    if (layout.type === 'tabs') {
      const groups = [...layout.tabs, ...(layout.overflow ? [layout.overflow] : [])];
      const current = groups[activeTab] ?? groups[0];
      return (
        <div className="native-form__tabs">
          <div className="native-form__tabs-nav">
            {groups.map((group, index) => <button key={group.label} type="button" className={`native-form__tab-button${index === activeTab ? ' native-form__tab-button--active' : ''}`} onClick={() => setActiveTab(index)}>{group.label}</button>)}
          </div>
          {current && renderFields(current.fields)}
        </div>
      );
    }
    if (layout.type === 'sections') {
      return <>{layout.sections.map(renderGroup)}{layout.overflow && renderGroup(layout.overflow)}</>;
    }
    return renderFields(layout.fields);
  };

  const detailGrid = options.detailFields ? (
    <NativeDetailGrid
      allowAdding={options.allowAdding}
      allowDeleting={options.allowDeleting}
      allowUpdating={options.allowUpdating}
      detailFields={options.detailFields}
      onRowsMutated={notifyDetailRowsMutated}
      remoteOptions={remoteOptions}
      rows={detailRows}
      onRowsChange={setNextDetailRows}
    />
  ) : null;

  return (
    <div className={`native-form${options.detailFields ? ' native-form--with-detail' : ''} ${options.className ?? ''}`}>
      {options.detailFields ? (
        <div className="native-form__sales-layout">
          <div className="native-form__master-panel">{renderLayout(formLayoutModel)}</div>
          <div className="native-form__detail-panel">{detailGrid}</div>
        </div>
      ) : (
        renderLayout(formLayoutModel)
      )}
    </div>
  );
});

NativeFormView.displayName = 'NativeFormView';
