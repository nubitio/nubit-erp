import { useMemo, useRef, useState } from 'react';
import { API_BASE_URL } from '@app/constants';
import { DataGridView, DialogView, FormView, type FormHandle, type GridHandle } from '@nubit/core';
import { CreditNoteDetailFields, CreditNoteFields } from './CreditNoteFields';
import toast from '@shared/lib/SharedUtils';
import { useEvents } from '@nubit/core';
import type { CreditNoteRow } from '@features/creditNote/lib/creditNoteActions';
import {
  creditNoteEvents,
  runOpenCreditNoteReceiptAction,
  runSendCreditNoteToSunatAction,
  runConsultCreditNoteCdrAction,
  runConsultCreditNoteCpeAction,
  useCreditNoteRelatedDocumentObserver,
  useCreditNoteSelectionState,
} from '@features/creditNote';
import type { DataRecord } from '@nubit/core';

const API_URL = API_BASE_URL + 'tax-documents';
const fields = CreditNoteFields();
const gridDetailFields = (parentRow: DataRecord) =>
  CreditNoteDetailFields((parentRow?.currency as string) ?? 'PEN');

export const CreditNotePage = () => {
  const [on] = useEvents();
  const gridRef = useRef<GridHandle>(null);
  const formRef = useRef<FormHandle>(null);
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

  const toolbar = useMemo(
    () => [
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
    [actionDeps],
  );

  return (
    <div id="wrapper">
      <DataGridView
        id="creditNote"
        ref={gridRef}
        title="Notas de crédito"
        toolbar={toolbar}
        url={API_URL + '?type=note'}
        detailUrl={API_URL + '/{id}/lines'}
        detailFields={gridDetailFields}
        allowAdd
        allowEdit
        allowDelete
        editDisabled={editDisabled}
        deleteDisabled={deleteDisabled}
        fields={fields}
        events={creditNoteEvents}
        onSelectionChanged={handleSelectionChanged}
      />
      <DialogView width={1200} height={720} events={creditNoteEvents}>
        <FormView
          ref={formRef}
          url={API_URL}
          fields={fields}
          detailUrl={API_URL + '/{id}/lines'}
          detailFields={formDetailFields}
          detailPropertyName="lines"
          allowAdding={false}
          allowDeleting
          allowUpdating
          requiredDetail
          events={creditNoteEvents}
        />
      </DialogView>
    </div>
  );
};
