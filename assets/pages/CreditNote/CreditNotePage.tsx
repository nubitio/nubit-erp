import './CreditNotePage.scss';
import { Constants } from '../../core/common/Constants';
import { DxDataGrid, DxDataGridEvents } from '../../core/datagrid/DxDataGrid';
import { DxDialog } from '../../core/dialog/DxDialog';
import { DxForm } from '../../core/form/DxForm';
import { CreditNoteFields } from './CreditNoteFields';
import { useRef } from 'react';
import { saleService } from '../../service/sale.service';
import notify from 'devextreme/ui/notify';

export const CreditNotePage = () => {
  const container = 'wrapper';
  const fields = CreditNoteFields();
  const gridRef = useRef<DxDataGridEvents>(null);

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

  const toolbar = [
    {
      text: 'Enviar a SUNAT',
      type: 'info',
      onClick: () => {
        let selectedRow = gridRef.current?.getSelectedRow();
        if (selectedRow) {
          if (selectedRow.envio_sunat === '0') {
            notify(
              {
                message: 'El comprobante ya fue enviado a SUNAT',
                type: 'warning',
                displayTime: 3000,
              },
              { position: 'top right' },
            );
            return;
          }

          gridRef.current?.showLoading('Enviando comprobante a SUNAT...');
          saleService
            .makeNote(selectedRow.nota_id)
            .subscribe((response) => {
              notify(
                {
                  message: response.data.message,
                  type: response.data.success ? 'success' : 'error',
                  displayTime: 3000,
                },
                { position: 'top right' },
              );
              gridRef.current?.refresh();
            })
            .add(() => {
              gridRef.current?.hideLoading();
            });
        } else {
          notify(
            {
              message: 'Debe seleccionar una nota',
              type: 'warning',
              displayTime: 3000,
            },
            { position: 'top right' },
          );
        }
      },
    },
    {
      text: 'Enviar por correo',
      type: 'success',
      onClick: () => {
        notify(
          {
            message: 'Funcionalidad no implementada',
            type: 'warning',
            displayTime: 3000,
          },
          { position: 'top right' },
        );
      },
    },
    {
      text: 'Ver',
      type: 'primary',
      onClick: () => {
        let selectedRow = gridRef.current?.getSelectedRow();
        let payload = {
          type: selectedRow?.tipo_id,
          id: selectedRow?.nota_id,
        };
        let payloadEncoded = btoa(JSON.stringify(payload));
        if (selectedRow) {
          window.open('receipt/' + payloadEncoded, '_blank');
        } else {
          notify(
            {
              message: 'Debe seleccionar una nota',
              type: 'warning',
              displayTime: 3000,
            },
            { position: 'top right' },
          );
        }
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
        fields={fields}
        events={EVENTS}
      />
      <DxDialog events={EVENTS}>
        <DxForm url={API_URL} fields={fields} events={EVENTS} />
      </DxDialog>
    </div>
  );
};
