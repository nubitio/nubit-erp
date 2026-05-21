import toast from '@shared/lib/SharedUtils';

type ToolbarAction = {
  text: string;
  type: string;
  onClick: () => void;
  disabled?: boolean;
};

type CreateSaleToolbarParams = {
  getSelectedSaleId: () => string | undefined;
  onPreview: (saleId: string) => void;
  onPrint: (saleId: string) => void;
  onVoid: (saleId: string) => void;
  onMakeInvoice: (saleId: string) => void;
  onClone: (saleId: string) => void;
  onReplaceRejected: (saleId: string) => void;
  onViewDocuments: (saleId: string) => void;
  printEnabled: boolean;
  cloneEnabled: boolean;
  replaceEnabled: boolean;
  documentsEnabled: boolean;
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
  onPreview,
  onPrint,
  onVoid,
  onMakeInvoice,
  onClone,
  onReplaceRejected,
  onViewDocuments,
  printEnabled,
  cloneEnabled,
  replaceEnabled,
  documentsEnabled,
}: CreateSaleToolbarParams): ToolbarAction[] => [
  {
    text: 'Vista previa',
    type: 'info',
    onClick: () => requireSelectedSale(getSelectedSaleId, onPreview),
  },
  {
    text: 'Imprimir',
    type: 'primary',
    onClick: () => requireSelectedSale(getSelectedSaleId, onPrint),
    disabled: !printEnabled,
  },
  {
    text: 'Anular',
    type: 'danger',
    onClick: () => requireSelectedSale(getSelectedSaleId, onVoid),
  },
  {
    text: 'Generar comprobante',
    type: 'warning',
    onClick: () => requireSelectedSale(getSelectedSaleId, onMakeInvoice),
  },
  {
    text: 'Duplicar',
    type: 'info',
    onClick: () => requireSelectedSale(getSelectedSaleId, onClone),
    disabled: !cloneEnabled,
  },
  {
    text: 'Reemitir',
    type: 'warning',
    onClick: () => requireSelectedSale(getSelectedSaleId, onReplaceRejected),
    disabled: !replaceEnabled,
  },
  {
    text: 'Comprobantes',
    type: 'secondary',
    onClick: () => requireSelectedSale(getSelectedSaleId, onViewDocuments),
    disabled: !documentsEnabled,
  },
];
