import { taxDocumentService } from '../services/taxDocumentService';

export type CreditNoteRow = {
  id: string;
  sunatStatus?: string;
  publicReceiptUrl?: string;
};

import type { ActionDeps } from './actionDeps';

const withSelectedRow = (
  deps: Pick<ActionDeps<CreditNoteRow>, 'getSelectedRow' | 'notify'>,
  emptyMessage: string,
  action: (row: CreditNoteRow) => void,
) => {
  const row = deps.getSelectedRow();
  if (!row) {
    deps.notify(emptyMessage, 'warning');
    return;
  }
  action(row);
};

export const runSendCreditNoteToSunatAction = (deps: ActionDeps<CreditNoteRow>) => {
  withSelectedRow(deps, 'Debe seleccionar una nota', (selectedRow) => {
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

export const runOpenCreditNoteReceiptAction = (
  deps: Pick<ActionDeps<CreditNoteRow>, 'getSelectedRow' | 'notify'>,
) => {
  withSelectedRow(deps, 'Debe seleccionar una nota', (selectedRow) => {
    if (!selectedRow.publicReceiptUrl) {
      deps.notify('La nota no tiene URL pública disponible', 'warning');
      return;
    }
    window.open(selectedRow.publicReceiptUrl, '_blank');
  });
};

export const runConsultCreditNoteCdrAction = (deps: ActionDeps<CreditNoteRow>) => {
  withSelectedRow(deps, 'Debe seleccionar una nota', (selectedRow) => {
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

export const runConsultCreditNoteCpeAction = (deps: ActionDeps<CreditNoteRow>) => {
  withSelectedRow(deps, 'Debe seleccionar una nota', (selectedRow) => {
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
