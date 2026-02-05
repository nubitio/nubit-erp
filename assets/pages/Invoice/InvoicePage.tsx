import './InvoicePage.scss';
import {Constants} from '../../core/common/Constants';
import {DxDataGrid, DxDataGridEvents} from '../../core/datagrid/DxDataGrid';
import {DxDialog} from '../../core/dialog/DxDialog';
import {DxForm} from '../../core/form/DxForm';
import {InvoiceFields} from './InvoiceFields';
import {useRef} from 'react';
import {taxDocumentService} from '../../service/taxDocument.service';
import toast from "../../core/utils/SharedUtils";

export const InvoicePage = () => {
  const container = 'wrapper';
  const fields = InvoiceFields();
  const gridRef = useRef<DxDataGridEvents>(null);

  const API_URL = Constants.API_BASE_URL + 'tax-documents';

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
          toast(
            "Debe seleccionar un comprobante",
            "warning",
          )
          return;
        }

        if (selectedRow.sunatStatus === '0') {
          toast(
            "El comprobante ya fue enviado a SUNAT",
            "warning",
          )
          return;
        }

        gridRef.current?.showLoading('Enviando comprobante a SUNAT...');
        taxDocumentService
          .sendDocument(selectedRow.id)
          .subscribe((response) => {
            toast(
              response.data.message,
              response.data.success ? "success" : "error"
            )
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
          toast(
            "Debe seleccionar un comprobante",
            "warning",
          )
          return;
        }

        gridRef.current?.showLoading('Enviando comprobante por correo...');
        taxDocumentService
          .sendEmail(selectedRow.id)
          .subscribe((data) => {
            toast(
              "El comprobante fue enviado por correo",
              "success"
            )
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
          toast(
            "Debe seleccionar un comprobante",
            "warning",
          )
          return;
        }
        gridRef.current?.showLoading('Consultando CDR...');
        taxDocumentService
          .consultDocument(selectedRow.id)
          .subscribe((response) => {
            toast(
              response.data.message,
              response.data.success ? "success" : "error"
            )
            gridRef.current?.refresh();
          })
          .add(() => gridRef.current?.hideLoading());
      },
    },
    {
      text: 'Consulta CPE',
      type: 'success',
      onClick: () => {
        const selectedRow = gridRef.current?.getSelectedRow();
        if (!selectedRow) {
          toast(
            "Debe seleccionar un comprobante",
            "warning",
          )
          return;
        }
        gridRef.current?.showLoading('Consultando CPE...');
        taxDocumentService
          .validateDocument(selectedRow.id)
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
      },
    },
  ];

  return (
    <div id={container}>
      <DxDataGrid
        id={'invoice'}
        ref={gridRef}
        title={'Comprobantes'}
        toolbar={toolbar}
        url={API_URL + '?type=invoice'}
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
