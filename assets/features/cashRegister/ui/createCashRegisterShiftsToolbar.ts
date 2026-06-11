import type { ResourceToolbarItems } from '@nubitio/crud';

export const createCashRegisterShiftsToolbar = (params: {
  onExportPdf: () => void;
}): ResourceToolbarItems => ({
  selection: [
    {
      text: 'Exportar PDF',
      icon: 'ph-printer',
      type: 'default',
      group: 'export',
      groupLabel: 'Exportar',
      onClick: params.onExportPdf,
    },
  ],
});
