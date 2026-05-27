import { useMemo, useRef, useState } from 'react';
import { getCoreApiBaseUrl } from '@nubit/core';

const API = getCoreApiBaseUrl();
import {
  SmartCrudPage,
  defineResource,
  type DataRecord,
  type FormHandle,
  type GridHandle,
} from '@nubit/crud';
import { CreditNoteDetailFields, CreditNoteFields } from './CreditNoteFields';
import toast from '@shared/lib/SharedUtils';
import { useEvents } from '@nubit/core';
import type { CreditNoteRow } from './lib/creditNoteActions';
import {
  runOpenCreditNoteReceiptAction,
  runSendCreditNoteToSunatAction,
  runConsultCreditNoteCdrAction,
  runConsultCreditNoteCpeAction,
} from './lib/creditNoteActions';
import {
  useCreditNoteRelatedDocumentObserver,
  useCreditNoteSelectionState,
} from './model';

const API_URL = API + 'tax-documents';
const fields = CreditNoteFields();

export const CreditNotePage = () => {
  const [on] = useEvents();
  const gridRef = useRef<GridHandle | null>(null);
  const formRef = useRef<FormHandle | null>(null);
  const { editDisabled, deleteDisabled, handleSelectionChanged } = useCreditNoteSelectionState();
  const [formDetailFields, setFormDetailFields] = useState(() => CreditNoteDetailFields());

  useCreditNoteRelatedDocumentObserver({
    on,
    apiUrl: API_URL,
    formRef,
    onCurrencyChange: (currency) => setFormDetailFields(CreditNoteDetailFields(currency)),
  });

  const actionDeps = useMemo(
    () => ({
      getSelectedRow: () => gridRef.current?.getSelectedRow() as CreditNoteRow | undefined,
      showLoading: (message?: string) => gridRef.current?.showLoading(message),
      hideLoading: () => gridRef.current?.hideLoading(),
      refreshGrid: () => gridRef.current?.refresh(),
      notify: (message: string, type: 'success' | 'error' | 'warning' | 'info') =>
        toast(message, type),
    }),
    [],
  );

  const resource = useMemo(
    () =>
      defineResource(API_URL + '?type=note', {
        id: 'creditNote',
        title: 'Notas de crédito',
        fields,
        gridDetail: {
          url: API_URL + '/{id}/lines',
          fields: (parentRow: DataRecord) =>
            CreditNoteDetailFields((parentRow?.currency as string) ?? 'PEN'),
        },
        formDetail: {
          url: API_URL + '/{id}/lines',
          fields: formDetailFields,
          propertyName: 'lines',
          allowAdding: false,
          allowDeleting: true,
          allowUpdating: true,
          required: true,
        },
        dialogWidth: 1200,
        dialogHeight: 720,
        permissions: { canAdd: true, canEdit: true, canDelete: true },
        toolbar: [
          {
            text: 'Enviar a SUNAT',
            type: 'info',
            onClick: () => runSendCreditNoteToSunatAction(actionDeps),
          },
          {
            text: 'Enviar por correo',
            type: 'success',
            onClick: () => toast('Funcionalidad no implementada', 'warning'),
          },
          { text: 'Ver', type: 'primary', onClick: () => runOpenCreditNoteReceiptAction(actionDeps) },
          {
            text: 'Consulta CDR',
            type: 'warning',
            onClick: () => runConsultCreditNoteCdrAction(actionDeps),
          },
          {
            text: 'Consulta CPE',
            type: 'success',
            onClick: () => runConsultCreditNoteCpeAction(actionDeps),
          },
        ],
      }),
    [actionDeps, formDetailFields],
  );

  return (
    <SmartCrudPage
      resource={resource}
      formRef={formRef}
      gridRef={gridRef}
      editDisabled={editDisabled}
      deleteDisabled={deleteDisabled}
      onSelectionChanged={handleSelectionChanged}
    />
  );
};
