type ToolbarAction = {
  text: string;
  type: string;
  onClick: () => void;
};

export const createCashRegisterToolbar = (params: {
  onViewShifts: () => void;
}): ToolbarAction[] => [
  {
    text: 'Ver turnos',
    type: 'info',
    onClick: params.onViewShifts,
  },
];
