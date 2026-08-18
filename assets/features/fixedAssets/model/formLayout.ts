import type { FormLayout } from '@nubitio/crud';

export const fixedAssetFormLayout: FormLayout = {
  type: 'sections',
  sections: [
    {
      label: 'Activo',
      fields: ['description', 'purchaseDate', 'sourceBill'],
    },
    {
      label: 'Costo y depreciación',
      fields: ['cost', 'usefulLifeMonths'],
    },
    {
      label: 'Notas',
      fields: ['note'],
    },
  ],
};
