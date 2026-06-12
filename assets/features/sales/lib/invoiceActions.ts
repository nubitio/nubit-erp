import { taxDocumentService } from '../services/taxDocumentService';

export type InvoiceRow = {
  id: string;
  createdAt?: string;
  sunatStatus?: string;
  publicReceiptUrl?: string;
  series?: string;
  number?: string | number;
  customer?: string;
};

import type { ActionDeps } from './actionDeps';

const withSelectedRow = (
  getSelectedRow: () => InvoiceRow | undefined,
  notify: ActionDeps<InvoiceRow>['notify'],
  action: (row: InvoiceRow) => void,
) => {
  const row = getSelectedRow();
  if (!row) {
    notify('Debe seleccionar un comprobante', 'warning');
    return;
  }
  action(row);
};

export const runSendInvoiceToSunatAction = (deps: ActionDeps<InvoiceRow>) => {
  withSelectedRow(deps.getSelectedRow, deps.notify, (selectedRow) => {
    if (selectedRow.sunatStatus === '0') {
      deps.notify('El comprobante ya fue enviado a SUNAT', 'warning');
      return;
    }

    deps.showLoading('Enviando comprobante a SUNAT...');
    taxDocumentService
      .sendDocument(selectedRow.id)
      .then((response) => {
        deps.notify(response.data.message, response.data.success ? 'success' : 'error');
        deps.refreshGrid();
      })
      .finally(() => deps.hideLoading());
  });
};

export const runSendInvoiceEmailAction = (deps: ActionDeps<InvoiceRow>) => {
  withSelectedRow(deps.getSelectedRow, deps.notify, (selectedRow) => {
    deps.showLoading('Enviando comprobante por correo...');
    taxDocumentService
      .sendEmail(selectedRow.id)
      .then(() => {
        deps.notify('El comprobante fue enviado por correo', 'success');
        deps.refreshGrid();
      })
      .finally(() => deps.hideLoading());
  });
};

export const runOpenInvoiceReceiptAction = (
  deps: Pick<ActionDeps<InvoiceRow>, 'getSelectedRow' | 'notify'>,
) => {
  withSelectedRow(deps.getSelectedRow, deps.notify, (selectedRow) => {
    if (!selectedRow.publicReceiptUrl) {
      deps.notify('El comprobante no tiene URL pública disponible', 'warning');
      return;
    }
    window.open(selectedRow.publicReceiptUrl, '_blank');
  });
};

export const runConsultInvoiceCdrAction = (deps: ActionDeps<InvoiceRow>) => {
  withSelectedRow(deps.getSelectedRow, deps.notify, (selectedRow) => {
    deps.showLoading('Consultando CDR...');
    taxDocumentService
      .consultDocument(selectedRow.id)
      .then((response) => {
        deps.notify(response.data.message, response.data.success ? 'success' : 'error');
        deps.refreshGrid();
      })
      .finally(() => deps.hideLoading());
  });
};

export const runConsultInvoiceCpeAction = (deps: ActionDeps<InvoiceRow>) => {
  withSelectedRow(deps.getSelectedRow, deps.notify, (selectedRow) => {
    deps.showLoading('Consultando CPE...');
    taxDocumentService
      .validateDocument(selectedRow.id)
      .then((response) => {
        deps.notify(response.data.message, response.data.success ? 'success' : 'error');
        deps.refreshGrid();
      })
      .finally(() => deps.hideLoading());
  });
};
