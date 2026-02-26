import './CreditNotePage.scss';
import { Constants } from '@platform/common/Constants';
import { DxDataGrid, DxDataGridEvents } from '@platform/datagrid/DxDataGrid';
import { DxDialog } from '@platform/dialog/DxDialog';
import { DxForm, DxFormEvents } from '@platform/form/DxForm';
import { CreditNoteDetailFields, CreditNoteFields } from './CreditNoteFields';
import { useRef } from 'react';
import toast from '@shared/lib/SharedUtils';
import { useEvents } from '@platform/event/EventHook';
import {
  createCreditNoteToolbar,
  creditNoteEvents,
  runOpenCreditNoteReceiptAction,
  runSendCreditNoteToSunatAction,
  useCreditNoteRelatedDocumentObserver,
  useCreditNoteSelectionState,
} from '@features/creditNote';

export const CreditNotePage = () => {
  const [on] = useEvents();
  const container = 'wrapper';
  const fields = CreditNoteFields();
  const detailFields = CreditNoteDetailFields();
  const gridRef = useRef<DxDataGridEvents>(null);
  const formRef = useRef<DxFormEvents>(null);
  const { editDisabled, deleteDisabled, handleSelectionChanged } = useCreditNoteSelectionState();

  const API_URL = Constants.API_BASE_URL + 'tax-documents';

  useCreditNoteRelatedDocumentObserver({ on, apiUrl: API_URL, formRef });

  const actionDeps = {
    getSelectedRow: () => gridRef.current?.getSelectedRow(),
    showLoading: (message?: string) => gridRef.current?.showLoading(message),
    hideLoading: () => gridRef.current?.hideLoading(),
    refreshGrid: () => gridRef.current?.refresh(),
    notify: (message: string, type: 'success' | 'error' | 'warning' | 'info') => toast(message, type),
  };

  const toolbar = createCreditNoteToolbar({
    onSendSunat: () => runSendCreditNoteToSunatAction(actionDeps),
    onSendEmail: () => toast('Funcionalidad no implementada', 'warning'),
    onView: () => runOpenCreditNoteReceiptAction(actionDeps),
  });

  return (
    <div id={container}>
      <DxDataGrid
        id={'creditNote'}
        ref={gridRef}
        title={'Notas de crédito'}
        toolbar={toolbar}
        url={API_URL + '?type=note'}
        detailUrl={API_URL + '/{id}/lines'}
        detailFields={detailFields}
        allowAdd={true}
        allowEdit={true}
        allowDelete={true}
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
          allowDeleting={true}
          allowUpdating={true}
          requiredDetail={true}
          events={creditNoteEvents}
          useIRI={true}
        />
      </DxDialog>
    </div>
  );
};
