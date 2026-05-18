import { MutableRefObject, useEffect, useRef } from 'react';
import { FormRef } from 'devextreme-react/form';
import { DataGridRef } from 'devextreme-react/data-grid';
import ArrayStore from 'devextreme/data/array_store';
import DataSource from 'devextreme/data/data_source';

import { FORM_ERRORS_EVENT, httpClient } from '@shared/api/HttpClient';
import toast from '@shared/lib/SharedUtils';
import { FORM_EVENTS } from '@core/form/FormEvents';
import { buildEmptyRow, normalizeFormData } from '@core/form/FormDataTransform';
import type { Field } from '@core/field/Field';
import type { FormEventNames } from '@core/event/EventTypes';
import type { UploadedFile } from '@core/form/UploadedFile';
import type { FormDataChangedHandler, FormDataRecord } from '@core/form/FormDataSnapshot';
import { hasDxFormInstance, setDxFormData } from './DxFormAccessorReads';

type FormError = { propertyPath: string; message: string };
type EventRowPayload = FormDataRecord & { row?: FormDataRecord };
type PendingAddPayload = { payload: EventRowPayload | undefined };
type OnFn = <T>(name: string, handler: (payload: T) => void) => unknown;
type EmitFn = <T>(name: string, payload?: T) => void;

export interface UseDxFormObserversOptions {
  dataSource: MutableRefObject<DataSource>;
  detailNoDataText: string;
  detailPropertyName: string;
  detailUrl?: string;
  emit: EmitFn;
  events?: FormEventNames;
  fields: Field[];
  formRef: MutableRefObject<FormRef | null>;
  gridRef: MutableRefObject<DataGridRef | null>;
  handleDelete: (payload: { row: FormDataRecord }) => void;
  handleSave: () => void;
  isEdit: MutableRefObject<boolean>;
  loadingText: string;
  on: OnFn;
  onFieldDataChanged?: FormDataChangedHandler;
  setDetailData: (data: FormDataRecord[]) => void;
  setError: (field: string, message: string) => void;
  uploadedFiles: MutableRefObject<UploadedFile[]>;
  validationErrorText: string;
}

export function useDxFormObservers(options: UseDxFormObserversOptions): void {
  const pendingAddPayload = useRef<PendingAddPayload | null>(null);

  const getIdField = () => options.fields.find((field) => field.isIdentity)?.name ?? '';
  const getData = (rawData: FormDataRecord) => normalizeFormData(rawData, options.fields);

  const applyAddPayload = (payload: EventRowPayload | undefined) => {
    if (!hasDxFormInstance(options.formRef)) return false;

    options.uploadedFiles.current = [];
    const store = options.dataSource.current?.store() as ArrayStore;
    store.clear();
    options.dataSource.current?.reload();

    if (payload) {
      const row = getData(payload);
      setDxFormData(options.formRef, row);
      options.onFieldDataChanged?.(row);
      if (Array.isArray(payload[options.detailPropertyName])) {
        options.setDetailData(payload[options.detailPropertyName] as FormDataRecord[]);
      }
    } else {
      const row = buildEmptyRow(options.fields);
      setDxFormData(options.formRef, row);
      options.onFieldDataChanged?.(row);
    }

    options.isEdit.current = false;
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
    options.on<FormError[]>(FORM_ERRORS_EVENT, (errors) => {
      toast(options.validationErrorText, 'error');
      errors.forEach((error) => {
        options.setError(error.propertyPath, error.message);
      });
    });

    options.on<boolean>(FORM_EVENTS.LOADING, (loading) => {
      if (loading) {
        options.gridRef.current?.instance().option('noDataText', '');
        options.gridRef.current?.instance().beginCustomLoading(options.loadingText);
      } else {
        options.gridRef.current?.instance().endCustomLoading();
        options.gridRef.current?.instance().option('noDataText', options.detailNoDataText);
      }
    });

    if (options.events?.ADD) {
      options.on(options.events.ADD, (payload: EventRowPayload | undefined) => {
        if (applyAddPayload(payload)) return;
        pendingAddPayload.current = { payload };
        window.setTimeout(flushPendingAddPayload, 0);
      });
    }

    if (options.events?.EDIT) {
      options.on(options.events.EDIT, (payload: { row: FormDataRecord }) => {
        const row = getData(payload.row);
        setDxFormData(options.formRef, row);
        options.onFieldDataChanged?.(row);
        options.uploadedFiles.current = [];
        options.isEdit.current = true;

        const detailId = row[getIdField()];
        const detailUrl =
          typeof detailId === 'string' || typeof detailId === 'number'
            ? options.detailUrl?.replace('{id}', String(detailId))
            : undefined;
        if (!detailUrl) return;

        options.setDetailData([]);
        options.emit(FORM_EVENTS.LOADING, true);
        httpClient
          .get<FormDataRecord[]>(detailUrl)
          .then((response) => {
            options.setDetailData(response.data);
          })
          .finally(() => options.emit(FORM_EVENTS.LOADING, false));
      });
    }

    if (options.events?.DELETE) {
      options.on(options.events.DELETE, (payload: { row: FormDataRecord }) =>
        options.handleDelete(payload),
      );
    }

    if (options.events?.SAVE) {
      options.on(options.events.SAVE, () => options.handleSave());
    }
  };

  const setupObserversRef = useRef(setupObservers);
  // eslint-disable-next-line react-hooks/refs -- subscriptions stay mounted but should call latest closures
  setupObserversRef.current = setupObservers;

  useEffect(() => {
    setupObserversRef.current();
    window.setTimeout(flushPendingAddPayload, 0);
  }, []);
}
