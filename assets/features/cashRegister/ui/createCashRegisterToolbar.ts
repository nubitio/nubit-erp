import type { ResourceToolbarItems } from '@nubit/crud';

export const createCashRegisterToolbar = (params: {
  onViewShifts: () => void;
}): ResourceToolbarItems => ({
  selection: [
    {
      text: 'Ver turnos',
      type: 'info',
      group: 'navigation',
      groupLabel: 'Navegación',
      onClick: params.onViewShifts,
    },
  ],
});
