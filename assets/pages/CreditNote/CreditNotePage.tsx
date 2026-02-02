import './CreditNotePage.scss';
import {Constants} from '../../core/common/Constants';
import {DxDataGrid, DxDataGridEvents} from '../../core/datagrid/DxDataGrid';
import {DxDialog} from '../../core/dialog/DxDialog';
import {DxForm} from '../../core/form/DxForm';
import {CreditNoteFields} from './CreditNoteFields';
import {useRef, useState} from 'react';
import toast from "../../core/utils/SharedUtils";
import {httpClient} from "../../core/http/HttpClient";

export const CreditNotePage = () => {
  const container = 'wrapper';
  const fields = CreditNoteFields();
  const gridRef = useRef<DxDataGridEvents>(null);
  const [editDisabled, setEditDisabled] = useState<boolean>(true);
  const [deleteDisabled, setDeleteDisabled] = useState<boolean>(true);

  const API_URL = Constants.API_BASE_URL + 'credit-notes';

  const EVENTS = {
    ADD: 'credit-note:add',
    EDIT: 'credit-note:edit',
    DELETE: 'credit-note:delete',
    SAVE: 'credit-note:save',
    CANCEL: 'credit-note:cancel',
    SUCCESS: 'credit-note:success',
    LOADING: 'credit-note:loading',
  };

  const isPending = (row: any): boolean => {
    const status = row?.envioSunat ?? row?.envio_sunat;
    return status === null || status === undefined || status === '' || status === '0' || status === 'pendiente';
  };

  const toolbar = [
    {
      text: 'Enviar a SUNAT',
      type: 'info',
      onClick: () => {
        const selectedRow = gridRef.current?.getSelectedRow();
        if (selectedRow) {
          if (selectedRow.envio_sunat === '0') {
            toast(
              "El comprobante ya fue enviado a SUNAT",
              "warning",
            )
            return;
          }

          gridRef.current?.showLoading('Enviando comprobante a SUNAT...');
          httpClient.post<any>(Constants.API_BASE_URL + 'credit-notes/' + selectedRow.id + '/send')
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
        const payload = {
          type: selectedRow?.tipoId ?? selectedRow?.tipo_id,
          id: selectedRow?.id ?? selectedRow?.nota_id,
        };
        const payloadEncoded = btoa(JSON.stringify(payload));
        window.open('receipt/' + payloadEncoded, '_blank');
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
        url={API_URL}
        allowAdd={true}
        allowEdit={true}
        allowDelete={true}
        editDisabled={editDisabled}
        deleteDisabled={deleteDisabled}
        fields={fields}
        events={EVENTS}
        onSelectionChanged={(e) => {
          const row = e.selectedRowsData?.[0];
          const pending = row ? isPending(row) : false;
          setEditDisabled(!pending);
          setDeleteDisabled(!pending);
        }}
      />
      <DxDialog events={EVENTS}>
        <DxForm url={API_URL} fields={fields} events={EVENTS}/>
      </DxDialog>
    </div>
  );
};
