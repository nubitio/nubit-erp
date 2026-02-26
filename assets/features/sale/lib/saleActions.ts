import { saleService } from '../services/saleService';

type ActionDeps = {
  showLoading: (message?: string) => void;
  hideLoading: () => void;
  refreshGrid: () => void;
  notify: (message: string, type: 'success' | 'error' | 'warning' | 'info') => void;
};

type VoidSaleDeps = ActionDeps & {
  askReason: () => Promise<string | null | undefined>;
};

type MakeInvoiceDeps = ActionDeps & {
  printSale: (saleId: string) => void;
};

export const runVoidSaleAction = async (saleId: string, deps: VoidSaleDeps) => {
  const reason = await deps.askReason();
  if (!reason || reason.trim() === '') {
    return;
  }

  deps.showLoading('Anulando comprobante...');
  saleService
    .voidSale(saleId, reason)
    .subscribe((response) => {
      deps.notify(response.data.message, response.data.success ? 'success' : 'error');
      deps.refreshGrid();
    })
    .add(() => {
      deps.hideLoading();
    });
};

export const runMakeInvoiceAction = (saleId: string, deps: MakeInvoiceDeps) => {
  deps.showLoading('Generando comprobante...');
  saleService
    .makeInvoice(saleId)
    .subscribe((response) => {
      deps.notify(response.data.message, response.data.success ? 'success' : 'error');
      deps.refreshGrid();
      deps.printSale(saleId);
    })
    .add(() => {
      deps.hideLoading();
    });
};
