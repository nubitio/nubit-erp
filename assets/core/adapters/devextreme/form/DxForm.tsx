import Form, { FormRef, GroupItem, Item, Tab, TabbedItem } from 'devextreme-react/form';
import type { FieldDataChangedEvent } from 'devextreme/ui/form';
import { Field } from '@core/field/Field';
import { FieldType } from '@core/field/FieldType';
import React, { forwardRef, useCallback, useEffect, useImperativeHandle, useRef } from 'react';
import { FORM_ERRORS_EVENT, httpClient } from '@shared/api/HttpClient';
import { useFormSubmit } from '@core/form/useFormSubmit';
import { DataGridRef } from 'devextreme-react/data-grid';
import ArrayStore from 'devextreme/data/array_store';
import DataSource from 'devextreme/data/data_source';
import { useEvents } from '@core/event/EventHook';
import { buildField } from './FormUtils';
import { useCoreTranslation } from '@core/i18n/useCoreTranslation';
import toast from '@shared/lib/SharedUtils';
import { buildEmptyRow, normalizeFormData } from '@core/form/DxFormDataTransform';
import { DxFormDetailGrid } from './DxFormDetailGrid';
import { useDxFormApi } from './useDxFormApi';
import type { FormHandle } from '@core/form/FormHandle';
import { FORM_EVENTS } from '@core/form/FormEvents';
import type { FormViewOptions } from '@core/form/FormViewOptions';

type FormRecord = Record<string, unknown>;
type FormError = { propertyPath: string; message: string };
type EventRowPayload = FormRecord & { row?: FormRecord };
type PendingAddPayload = { payload: EventRowPayload | undefined };

export type DxFormOptions = FormViewOptions;

export type DxFormEvents = FormHandle;

export function syncComputedValuesIntoFormData(
  formData: Record<string, unknown>,
  computedFieldNames: string[],
  computedValues: Record<string, unknown>,
): { changed: boolean; nextFormData: Record<string, unknown> } {
  let changed = false;
  const nextFormData = { ...formData };

  computedFieldNames.forEach((name) => {
    const hasComputedValue = Object.prototype.hasOwnProperty.call(computedValues, name);
    const value = hasComputedValue ? computedValues[name] : undefined;

    if (nextFormData[name] !== value) {
      nextFormData[name] = value;
      changed = true;
    }
  });

  return { changed, nextFormData };
}

function validateDetailRows(detailRows: FormRecord[], detailFields: Field[]): boolean {
  return detailRows.every((row) =>
    detailFields.every((field) => {
      const value = row[field.name];

      if (field.required && (value === null || value === undefined || value === '')) {
        return false;
      }

      if (field.type === FieldType.NUMBER || field.type === FieldType.CURRENCY) {
        const numericValue = Number(value ?? 0);

        if (field.required && Number.isNaN(numericValue)) {
          return false;
        }

        const rangeValidators = field.validators.filter((validator) => validator.type === 'range');
        for (const validator of rangeValidators) {
          const { min, max } = validator.options;
          if (min !== undefined && numericValue < min) {
            return false;
          }
          if (max !== undefined && numericValue > max) {
            return false;
          }
        }
      }

      return true;
    }),
  );
}

export type UploadedFile = {
  name: string;
  iri: string;
};

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

  const validate = () => {
    const formValidation = formRef.current?.instance().validate();
    const formValid = formValidation?.isValid ?? true;
    let gridValid = !gridRef.current?.instance().hasEditData();
    let detailRequiredMissing = false;
    let detailRowsValid = true;

    if (options.requiredDetail) {
      gridValid = (gridRef.current?.instance().totalCount() ?? 0) > 0;
      if (!gridValid) {
        detailRequiredMissing = true;
      }
    }

    if (options.detailFields) {
      const detailRows = (gridRef.current?.instance().getDataSource().items() ??
        []) as FormRecord[];
      detailRowsValid = validateDetailRows(detailRows, options.detailFields);
    }

    if (!formValid || !gridValid || !detailRowsValid) {
      toast(
        detailRequiredMissing && formValid ? detailNoDataText : t('form.validationError'),
        detailRequiredMissing && formValid ? 'warning' : 'error',
      );
    }

    return formValid && gridValid && detailRowsValid;
  };

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

  /**
   * Returns the subset of `fields` whose `name` is included in `names`.
   * Preserves the original `fields` array order.
   */
  const pickFields = (names: string[]): Field[] =>
    options.fields.filter((f) => names.includes(f.name));

  /**
   * Returns fields that are NOT present in any of the provided name groups.
   * Used to collect "overflow" fields not assigned to any tab or section.
   */
  const remainingFields = (assignedNames: string[]): Field[] =>
    options.fields.filter((f) => !assignedNames.includes(f.name));

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

  /**
   * After the form mounts, apply `collapsible` / `collapsed` options to GroupItem sections.
   * The DevExtreme React wrapper for GroupItem does not expose these as JSX props, so we use
   * the imperative `itemOption` API once the form has rendered.
   */
  useEffect(() => {
    if (options.formLayout?.type !== 'sections') return;
    options.formLayout.sections.forEach((section) => {
      try {
        formRef.current
          ?.instance()
          .itemOption(section.label, 'collapsible', section.collapsible ?? false);
        if (section.defaultCollapsed) {
          formRef.current?.instance().itemOption(section.label, 'collapsed', true);
        }
      } catch {
        // itemOption may fail if the section name is not registered; ignore silently
      }
    });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    setupObserversRef.current();
    window.setTimeout(flushPendingAddPayload, 0);
  }, []);

  useEffect(() => {
    const instance = formRef.current?.instance();
    if (!instance || computedFieldNames.length === 0) {
      return;
    }

    const currentFormData = (instance.option('formData') ?? null) as Record<string, unknown> | null;
    if (currentFormData == null) {
      return;
    }

    const { changed, nextFormData } = syncComputedValuesIntoFormData(
      currentFormData,
      computedFieldNames,
      computedValues ?? {},
    );

    if (!changed) {
      return;
    }

    instance.option('formData', nextFormData);
    onFieldDataChanged?.(nextFormData);
  }, [computedFieldNames, computedValues, onFieldDataChanged]);

  const api = useDxFormApi({ formRef, gridRef, isEdit }, options.fields);
  // Destructure methods used by internal observers
  const { setDetailData, setError } = api;

  /**
   * Stable handler for DevExtreme Form's onFieldDataChanged event.
   * Reads the full formData snapshot from the form instance and forwards it
   * to the optional `onFieldDataChanged` prop — enabling reactive rule evaluation
   * (visibleWhen / disabledWhen / computed) in SmartCrudPage.
   */
  const handleFieldDataChanged = useCallback(
    (e: FieldDataChangedEvent) => {
      const snapshot = (e.component.option('formData') ?? {}) as Record<string, unknown>;
      onFieldDataChanged?.(snapshot);
    },
    [onFieldDataChanged],
  );

  useImperativeHandle(ref, () => ({
    ...api,
    // Override with DxForm-specific validate that includes detail grid validation + toast
    validate,
  }));

  const renderForm = () => {
    const fieldChangeHandler = onFieldDataChanged ? handleFieldDataChanged : undefined;

    if ((options.detailFields?.length ?? 0) > 0) {
      const sidebarColCount = 1;
      return (
        <Form
          ref={formRef}
          className={options.className}
          labelMode={'floating'}
          onFieldDataChanged={fieldChangeHandler}
        >
          <Item itemType="group" colCount={4}>
            <GroupItem colCount={sidebarColCount}>{buildFields(options.fields)}</GroupItem>
            <GroupItem colSpan={3}>
              <DxFormDetailGrid
                gridRef={gridRef}
                dataSource={data.current}
                detailFields={options.detailFields ?? []}
                detailIdField={getDetailIdField()}
                noDataText={detailNoDataText}
                allowAdding={options.allowAdding}
                allowUpdating={options.allowUpdating}
                allowDeleting={options.allowDeleting}
                emit={emit}
              />
            </GroupItem>
          </Item>
        </Form>
      );
    }

    // ── Tabs layout ──────────────────────────────────────────────────────────
    if (options.formLayout?.type === 'tabs') {
      const { tabs } = options.formLayout;
      const allAssigned = tabs.flatMap((t) => t.fields);
      const overflow = remainingFields(allAssigned);
      return (
        <Form
          ref={formRef}
          className={options.className}
          labelMode={'floating'}
          onFieldDataChanged={fieldChangeHandler}
        >
          <TabbedItem>
            {tabs.map((tab) => (
              <Tab key={tab.label} title={tab.label} icon={tab.icon}>
                {buildFields(pickFields(tab.fields))}
              </Tab>
            ))}
            {overflow.length > 0 && <Tab title={t('form.groupOther')}>{buildFields(overflow)}</Tab>}
          </TabbedItem>
        </Form>
      );
    }

    // ── Sections layout ──────────────────────────────────────────────────────
    if (options.formLayout?.type === 'sections') {
      const { sections } = options.formLayout;
      const allAssigned = sections.flatMap((s) => s.fields);
      const overflow = remainingFields(allAssigned);
      return (
        <Form
          ref={formRef}
          className={options.className}
          labelMode={'floating'}
          onFieldDataChanged={fieldChangeHandler}
        >
          {sections.map((section) => (
            // caption is used as the item name so itemOption(section.label, ...) works
            <GroupItem key={section.label} caption={section.label}>
              {buildFields(pickFields(section.fields))}
            </GroupItem>
          ))}
          {overflow.length > 0 && <GroupItem>{buildFields(overflow)}</GroupItem>}
        </Form>
      );
    }

    // ── Default flat layout ───────────────────────────────────────────────────
    return (
      <Form
        ref={formRef}
        className={options.className}
        labelMode={'floating'}
        onFieldDataChanged={fieldChangeHandler}
      >
        <GroupItem>{buildFields(options.fields)}</GroupItem>
      </Form>
    );
  };

  // eslint-disable-next-line react-hooks/refs -- renderForm accesses data.current (DevExtreme ArrayStore) used as DataGrid dataSource; intentional mutable ref
  return <React.Fragment>{renderForm()}</React.Fragment>;
});

DxForm.displayName = 'DxForm';
