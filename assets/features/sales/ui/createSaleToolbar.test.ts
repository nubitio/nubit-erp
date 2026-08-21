import { describe, expect, it, vi } from 'vitest';
import { createSaleToolbar } from './createSaleToolbar';

vi.mock('@shared/lib/SharedUtils', () => ({
  default: vi.fn(),
}));

const baseParams = {
  getSelectedSaleId: () => 'sale-1',
  onPreview: vi.fn(),
  onPrint: vi.fn(),
  onVoid: vi.fn(),
  onMakeInvoice: vi.fn(),
  onClone: vi.fn(),
  onReplaceRejected: vi.fn(),
  onViewDocuments: vi.fn(),
  printEnabled: true,
  cloneEnabled: true,
  replaceEnabled: true,
  documentsEnabled: true,
};

describe('createSaleToolbar', () => {
  it('omits Explicar con IA when the callback is missing', () => {
    const toolbar = createSaleToolbar(baseParams);
    expect(toolbar.selection?.map((item) => item.text)).not.toContain('Explicar con IA');
  });

  it('includes Explicar con IA when the callback is provided', () => {
    const toolbar = createSaleToolbar({
      ...baseParams,
      onExplainWithAi: vi.fn(),
    });
    expect(toolbar.selection?.map((item) => item.text)).toContain('Explicar con IA');
  });
});
