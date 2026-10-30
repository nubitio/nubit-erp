import toast from '@shared/lib/SharedUtils';
import type { ResourceToolbarItems } from '@nubitio/crud';

type CreateSaleToolbarParams = {
  getSelectedSaleId: () => string | undefined;
  onPreview: (saleId: string) => void;
  onPrint: (saleId: string) => void;
  onVoid: (saleId: string) => void;
  onMakeInvoice: (saleId: string) => void;
  onClone: (saleId: string) => void;
  onReplaceRejected: (saleId: string) => void;
  onViewDocuments: (saleId: string) => void;
  onExplainWithAi?: (saleId: string) => void;
  printEnabled: boolean;
  cloneEnabled: boolean;
  replaceEnabled: boolean;
  documentsEnabled: boolean;
  canCreate?: boolean;
};

const requireSelectedSale = (
  getSelectedSaleId: () => string | undefined,
  action: (saleId: string) => void,
) => {
  const saleId = getSelectedSaleId();
  if (!saleId) {
    toast('Debe seleccionar una venta', 'warning');
    return;
  }

  action(saleId);
};

export const createSaleToolbar = ({
  getSelectedSaleId,
  onMakeInvoice,
  onReplaceRejected,
  onViewDocuments,
  onExplainWithAi,
  replaceEnabled,
  documentsEnabled,
  canCreate = true,
}: CreateSaleToolbarParams): ResourceToolbarItems => ({
  selection: [
    {
      text: 'Generar comprobante',
      visible: canCreate,
      type: 'warning',
      group: 'documents',
      groupLabel: 'Comprobantes',
      onClick: () => requireSelectedSale(getSelectedSaleId, onMakeInvoice),
    },
    {
      text: 'Comprobantes',
      type: 'secondary',
      group: 'documents',
      groupLabel: 'Comprobantes',
      onClick: () => requireSelectedSale(getSelectedSaleId, onViewDocuments),
      disabled: !documentsEnabled,
    },
    {
      text: 'Reemitir',
      visible: canCreate,
      type: 'warning',
      group: 'edit',
      groupLabel: 'Edición',
      onClick: () => requireSelectedSale(getSelectedSaleId, onReplaceRejected),
      disabled: !replaceEnabled,
    },
    ...(onExplainWithAi
      ? [
          {
            text: 'Explicar con IA',
            type: 'secondary',
            group: 'documents',
            groupLabel: 'Comprobantes',
            onClick: () => requireSelectedSale(getSelectedSaleId, onExplainWithAi),
          },
        ]
      : []),
  ],
});
