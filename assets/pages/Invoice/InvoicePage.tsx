import './InvoicePage.scss';
import {Constants} from '../../core/common/Constants';
import {DxDataGrid, DxDataGridEvents} from '../../core/datagrid/DxDataGrid';
import {DxDialog} from '../../core/dialog/DxDialog';
import {DxForm} from '../../core/form/DxForm';
import {InvoiceFields} from './InvoiceFields';
import {useRef} from 'react';
import {invoiceService} from '../../service/invoice.service';
import notify from 'devextreme/ui/notify';
import toast from "../../core/utils/SharedUtils";

export const InvoicePage = () => {
  const container = 'wrapper';
  const fields = InvoiceFields();
  const gridRef = useRef<DxDataGridEvents>(null);

  const API_URL = Constants.API_BASE_URL + 'invoices';

  const EVENTS = {
    ADD: 'invoice:add',
    EDIT: 'invoice:edit',
    DELETE: 'invoice:delete',
    SAVE: 'invoice:save',
    CANCEL: 'invoice:cancel',
    SUCCESS: 'invoice:success',
    LOADING: 'invoice:loading',
  };

  const toolbar = [
    {
      text: 'Enviar a SUNAT',
      type: 'info',
      onClick: () => {
        const selectedRow = gridRef.current?.getSelectedRow();
        if (!selectedRow) {
          notify(
            {
              message: 'Seleccione un comprobante',
              type: 'warning',
              displayTime: 3000,
            },
            {position: 'top right'},
          );
          return;
        }

        if (selectedRow.estado_sunat === '0') {
          notify(
            {
              message: 'El comprobante ya fue enviado a SUNAT',
              type: 'warning',
              displayTime: 3000,
            },
            {position: 'top right'},
          );
          return;
        }

        gridRef.current?.showLoading('Enviando comprobante a SUNAT...');
        invoiceService
          .sendInvoice(selectedRow.saleId)
          .subscribe((response) => {
            notify(
              {
                message: response.data.message,
                type: response.data.success ? 'success' : 'error',
                displayTime: 3000,
              },
              {position: 'top right'},
            );
            gridRef.current?.refresh();
          })
          .add(() => gridRef.current?.hideLoading());
      },
    },
    {
      text: 'Enviar por correo',
      type: 'success',
      onClick: () => {
        const selectedRow = gridRef.current?.getSelectedRow();
        if (!selectedRow) {
          notify(
            {
              message: 'Seleccione un comprobante',
              type: 'warning',
              displayTime: 3000,
            },
            {position: 'top right'},
          );
          return;
        }

        gridRef.current?.showLoading('Enviando comprobante por correo...');
        invoiceService
          .sendEmail(selectedRow.saleId)
          .subscribe((data) => {
            notify(
              {
                message: 'El comprobante fue enviado por correo',
                type: 'success',
                displayTime: 3000,
              },
              {position: 'top right'},
            );
            gridRef.current?.refresh();
          })
          .add(() => gridRef.current?.hideLoading());
      },
    },
    {
      text: 'Ver',
      type: 'primary',
      onClick: () => {
        const selectedRow = gridRef.current?.getSelectedRow();
        if (!selectedRow) {
          toast(
            "Debe seleccionar un comprobante",
            "warning",
          )
          return;
        }
        window.open(selectedRow.publicReceiptUrl, '_blank');
      },
    },
    {
      text: 'Consulta CDR',
      type: 'warning',
      onClick: () => {
        const selectedRow = gridRef.current?.getSelectedRow();
        if (!selectedRow) {
          notify(
            {
              message: 'Seleccione un comprobante',
              type: 'warning',
              displayTime: 3000,
            },
            {position: 'top right'},
          );
          return;
        }
        gridRef.current?.showLoading('Consultando CDR...');
        invoiceService
          .consultInvoice(selectedRow.saleId)
          .subscribe((response) => {
            notify(
              {
                message: response.data.message,
                type: 'success',
                displayTime: 3000,
              },
              {position: 'top right'},
            );
            gridRef.current?.refresh();
          })
          .add(() => gridRef.current?.hideLoading());
      },
    },
    {
      text: 'Consulta CPE',
      type: 'success',
      onClick: () => {
        const selectedRows = gridRef.current?.getSelectedRowKeys();
        if (!selectedRows || selectedRows.length === 0) {
          notify(
            {
              message: 'Seleccione uno o más comprobantes',
              type: 'warning',
              displayTime: 3000,
            },
            {position: 'top right'},
          );
          return;
        }
        gridRef.current?.showLoading('Consultando CPE...');
        selectedRows.forEach((id: any) => {
          invoiceService
            .validateInvoice(id)
            .subscribe((response) => {
              console.log(response.data);
            })
            .add(() => {
              gridRef.current?.hideLoading();
              gridRef.current?.refresh();
            });
        });
      },
    },
  ];

  return (
    <div id={container}>
      <DxDataGrid
        id={'invoice'}
        ref={gridRef}
        title={'Comprobantes de pago'}
        toolbar={toolbar}
        url={API_URL}
        fields={fields}
        events={EVENTS}
        sort={[{selector: 'createdAt', desc: true}]}
      />
      <DxDialog events={EVENTS}>
        <DxForm url={API_URL} fields={fields} events={EVENTS}/>
      </DxDialog>
    </div>
  );
};
