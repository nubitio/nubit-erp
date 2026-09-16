import { describe, expect, it, vi } from 'vitest';
import { runMakeInvoiceAction } from './saleActions';
import { saleService } from '../services/saleService';
import { voucherTypePrompt } from '../components/VoucherTypeDialog';

vi.mock('../components/VoucherTypeDialog', () => ({
  voucherTypePrompt: vi.fn(),
}));

vi.mock('../services/saleService', () => ({
  saleService: {
    makeInvoice: vi.fn(),
  },
}));

const baseDeps = () => ({
  showLoading: vi.fn(),
  hideLoading: vi.fn(),
  refreshGrid: vi.fn(),
  notify: vi.fn(),
  printSale: vi.fn(),
});

describe('runMakeInvoiceAction', () => {
  it('does not call the API when the voucher-type prompt is cancelled', async () => {
    vi.mocked(voucherTypePrompt).mockResolvedValue(null);
    const deps = baseDeps();

    await runMakeInvoiceAction('sale-1', deps);

    expect(saleService.makeInvoice).not.toHaveBeenCalled();
    expect(deps.showLoading).not.toHaveBeenCalled();
  });

  it('passes the chosen voucher type through to the API', async () => {
    vi.mocked(voucherTypePrompt).mockResolvedValue('BOLETA');
    vi.mocked(saleService.makeInvoice).mockResolvedValue({
      data: { success: true, message: 'ok', data: null },
    } as never);
    const deps = baseDeps();

    await runMakeInvoiceAction('sale-1', deps);

    expect(saleService.makeInvoice).toHaveBeenCalledWith('sale-1', 'BOLETA');
  });
});
