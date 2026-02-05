import './CreditNotePage.scss';
import {Constants} from '../../core/common/Constants';
import {DxDataGrid, DxDataGridEvents} from '../../core/datagrid/DxDataGrid';
import {DxDialog} from '../../core/dialog/DxDialog';
import {DxForm, DxFormEvents, FORM_EVENTS} from '../../core/form/DxForm';
import {CreditNoteDetailFields, CreditNoteFields} from './CreditNoteFields';
import {useEffect, useRef, useState} from 'react';
import toast from "../../core/utils/SharedUtils";
import {taxDocumentService} from "../../service/taxDocument.service";
import {useEvents} from "../../core/event/EventHook";
import {httpClient} from "../../core/http/HttpClient";

export const CreditNotePage = () => {
  const [on] = useEvents();
  const container = 'wrapper';
  const fields = CreditNoteFields();
  const detailFields = CreditNoteDetailFields();
  const gridRef = useRef<DxDataGridEvents>(null);
  const formRef = useRef<DxFormEvents>(null);
  const [editDisabled, setEditDisabled] = useState<boolean>(true);
  const [deleteDisabled, setDeleteDisabled] = useState<boolean>(true);

  const API_URL = Constants.API_BASE_URL + 'tax-documents';

  const EVENTS = {
    ADD: 'credit-note:add',
    EDIT: 'credit-note:edit',
    DELETE: 'credit-note:delete',
    SAVE: 'credit-note:save',
    CANCEL: 'credit-note:cancel',
    SUCCESS: 'credit-note:success',
    LOADING: 'credit-note:loading',
  };

  useEffect(() => {
    on<{ field: string; value: any }>(FORM_EVENTS.FIELD_CHANGED, ({field, value}) => {
      if (field !== 'relatedTaxDocument') return;
      if (!value) {
        formRef.current?.setDetailData([]);
        return;
      }
      httpClient
        .get<any>(`${API_URL}/${value}/lines`)
        .subscribe((response) => {
          formRef.current?.setDetailData(response.data ?? []);
        });
    });
  }, []);

  const toolbar = [
    {
      text: 'Enviar a SUNAT',
      type: 'info',
      onClick: () => {
        const selectedRow = gridRef.current?.getSelectedRow();
        if (selectedRow) {
          if (selectedRow.sunatStatus === '0') {
            toast(
              "El comprobante ya fue enviado a SUNAT",
              "warning",
            )
            return;
          }

          gridRef.current?.showLoading('Enviando comprobante a SUNAT...');
          taxDocumentService.sendDocument(selectedRow.id)
            .subscribe((response) => {
              toast(
                response.data.message,
                response.data.success ? "success" : "error"
              )
              gridRef.current?.refresh();
            })
            .add(() => {
              gridRef.current?.hideLoading();
            });
        } else {
          toast(
            "Debe seleccionar una nota",
            "warning",
          )
        }
      },
    },
    {
      text: 'Enviar por correo',
      type: 'success',
      onClick: () => {
        toast(
          "Funcionalidad no implementada",
          "warning",
        )
      },
    },
    {
      text: 'Ver',
      type: 'primary',
      onClick: () => {
        const selectedRow = gridRef.current?.getSelectedRow();
        if (!selectedRow) {
          toast(
            "Debe seleccionar una nota",
            "warning"
          )
          return;
        }
        window.open(selectedRow.publicReceiptUrl, '_blank');
      },
    },
  ];

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
        events={EVENTS}
        onSelectionChanged={(e) => {
          const selectedRow = e.component.getSelectedRowsData()[0];
          const isPending = selectedRow.sunatStatus === undefined;
          setEditDisabled(!isPending);
          setDeleteDisabled(!isPending);
        }}
      />
      <DxDialog width={1200} height={720} events={EVENTS}>
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
          events={EVENTS}
          useIRI={true}
        />
      </DxDialog>
    </div>
  );
};
