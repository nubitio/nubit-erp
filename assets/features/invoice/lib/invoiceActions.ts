import { taxDocumentService } from '../services/taxDocumentService';

export type InvoiceRow = {
  id: string;
  sunatStatus?: string;
  publicReceiptUrl?: string;
};

type ActionDeps = {
  getSelectedRow: () => InvoiceRow | undefined;
  showLoading: (message?: string) => void;
  hideLoading: () => void;
  refreshGrid: () => void;
  notify: (message: string, type: 'success' | 'error' | 'warning' | 'info') => void;
};

const withSelectedRow = (
  getSelectedRow: () => InvoiceRow | undefined,
  notify: ActionDeps['notify'],
  action: (row: InvoiceRow) => void,
) => {
  const row = getSelectedRow();
  if (!row) {
    notify('Debe seleccionar un comprobante', 'warning');
    return;
  }
  action(row);
};

export const runSendInvoiceToSunatAction = (deps: ActionDeps) => {
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

export const runSendInvoiceEmailAction = (deps: ActionDeps) => {
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
  deps: Pick<ActionDeps, 'getSelectedRow' | 'notify'>,
) => {
  withSelectedRow(deps.getSelectedRow, deps.notify, (selectedRow) => {
    if (!selectedRow.publicReceiptUrl) {
      deps.notify('El comprobante no tiene URL pública disponible', 'warning');
      return;
    }
    window.open(selectedRow.publicReceiptUrl, '_blank');
  });
};

export const runConsultInvoiceCdrAction = (deps: ActionDeps) => {
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

export const runConsultInvoiceCpeAction = (deps: ActionDeps) => {
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
