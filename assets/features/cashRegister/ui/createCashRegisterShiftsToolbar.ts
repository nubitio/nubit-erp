type ToolbarAction = {
  text: string;
  icon?: string;
  type: string;
  onClick: () => void;
};

export const createCashRegisterShiftsToolbar = (params: {
  onExportPdf: () => void;
}): ToolbarAction[] => [
  {
    text: 'Exportar PDF',
    icon: 'print',
    type: 'default',
    onClick: params.onExportPdf,
  },
];
