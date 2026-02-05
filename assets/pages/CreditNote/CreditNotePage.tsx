import './CreditNotePage.scss';
import {Constants} from '../../core/common/Constants';
import {DxDataGrid, DxDataGridEvents} from '../../core/datagrid/DxDataGrid';
import {DxDialog} from '../../core/dialog/DxDialog';
import {DxForm} from '../../core/form/DxForm';
import {CreditNoteFields} from './CreditNoteFields';
import {useRef, useState} from 'react';
import toast from "../../core/utils/SharedUtils";
import {taxDocumentService} from "../../service/taxDocument.service";

export const CreditNotePage = () => {
  const container = 'wrapper';
  const fields = CreditNoteFields();
  const gridRef = useRef<DxDataGridEvents>(null);
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
      <DxDialog events={EVENTS}>
        <DxForm url={API_URL} fields={fields} events={EVENTS}/>
      </DxDialog>
    </div>
  );
};
