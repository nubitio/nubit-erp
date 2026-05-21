import { voidPrompt } from '../components/VoidDialog';
import { saleService } from '../services/saleService';

type ActionDeps = {
  showLoading: (message?: string) => void;
  hideLoading: () => void;
  refreshGrid: () => void;
  notify: (message: string, type: 'success' | 'error' | 'warning' | 'info') => void;
};

type VoidSaleDeps = ActionDeps;

type MakeInvoiceDeps = ActionDeps & {
  printSale: (saleId: string) => void;
};

type MakeInvoiceResponseData = {
  detraccionMonto?: string | number | null;
  detraccionCodigo?: string | null;
};

export const runVoidSaleAction = async (saleId: string, deps: VoidSaleDeps) => {
  const result = await voidPrompt();
  if (!result) return;

  deps.showLoading('Anulando comprobante...');
  saleService
    .voidSale(saleId, result.reason, result.restoreStock)
    .then((response) => {
      deps.notify(response.data.message, response.data.success ? 'success' : 'error');
      deps.refreshGrid();
    })
    .finally(() => {
      deps.hideLoading();
    });
};

export const runReplaceRejectedAction = (saleId: string, deps: ActionDeps) => {
  deps.showLoading('Generando comprobante de reemplazo...');
  saleService
    .replaceRejected(saleId)
    .then((response) => {
      deps.notify(response.data.message, response.data.success ? 'success' : 'error');
      deps.refreshGrid();
    })
    .finally(() => {
      deps.hideLoading();
    });
};

export const runMakeInvoiceAction = (saleId: string, deps: MakeInvoiceDeps) => {
  deps.showLoading('Generando comprobante...');
  saleService
    .makeInvoice(saleId)
    .then((response) => {
      deps.notify(response.data.message, response.data.success ? 'success' : 'error');
      const invoiceData = (response.data.data ?? null) as MakeInvoiceResponseData | null;
      if (response.data.success && invoiceData?.detraccionMonto != null) {
        const monto = Number(invoiceData.detraccionMonto).toFixed(2);
        const codigo = invoiceData.detraccionCodigo ?? '';
        deps.notify(`Detracción aplicada: S/ ${monto} — Código SUNAT: ${codigo}`, 'info');
      }
      deps.refreshGrid();
      deps.printSale(saleId);
    })
    .finally(() => {
      deps.hideLoading();
    });
};
