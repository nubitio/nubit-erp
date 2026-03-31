import { useMemo, useRef } from 'react';
import { Constants } from '@core/common/Constants';
import { DxDataGrid, type DxDataGridEvents } from '@core/datagrid/DxDataGrid';
import { DxDialog } from '@core/dialog/DxDialog';
import { DxForm, type DxFormEvents } from '@core/form/DxForm';
import { CreditNoteDetailFields, CreditNoteFields } from './CreditNoteFields';
import toast from '@shared/lib/SharedUtils';
import { useEvents } from '@core/event/EventHook';
import {
  creditNoteEvents,
  runOpenCreditNoteReceiptAction,
  runSendCreditNoteToSunatAction,
  runConsultCreditNoteCdrAction,
  runConsultCreditNoteCpeAction,
  useCreditNoteRelatedDocumentObserver,
  useCreditNoteSelectionState,
} from '@features/creditNote';

const API_URL = Constants.API_BASE_URL + 'tax-documents';
const fields = CreditNoteFields();
const detailFields = CreditNoteDetailFields();

export const CreditNotePage = () => {
  const [on] = useEvents();
  const gridRef = useRef<DxDataGridEvents>(null);
  const formRef = useRef<DxFormEvents>(null);
  const { editDisabled, deleteDisabled, handleSelectionChanged } = useCreditNoteSelectionState();

  useCreditNoteRelatedDocumentObserver({ on, apiUrl: API_URL, formRef });

  const actionDeps = useMemo(
    () => ({
      getSelectedRow: () => gridRef.current?.getSelectedRow(),
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
      <DxDataGrid
        id="creditNote"
        ref={gridRef}
        title="Notas de crédito"
        toolbar={toolbar}
        url={API_URL + '?type=note'}
        detailUrl={API_URL + '/{id}/lines'}
        detailFields={detailFields}
        allowAdd
        allowEdit
        allowDelete
        editDisabled={editDisabled}
        deleteDisabled={deleteDisabled}
        fields={fields}
        events={creditNoteEvents}
        onSelectionChanged={handleSelectionChanged}
      />
      <DxDialog width={1200} height={720} events={creditNoteEvents}>
        <DxForm
          ref={formRef}
          url={API_URL}
          fields={fields}
          detailUrl={API_URL + '/{id}/lines'}
          detailFields={detailFields}
          detailPropertyName="lines"
          allowAdding={false}
          allowDeleting
          allowUpdating
          requiredDetail
          events={creditNoteEvents}
        />
      </DxDialog>
    </div>
  );
};
