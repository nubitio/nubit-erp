import type { FormLayout } from '@nubitio/crud';

export const bankAccountFormLayout: FormLayout = {
  type: 'sections',
  sections: [
    {
      label: 'Cuenta',
      fields: ['bank', 'alias', 'accountNumber', 'cci', 'currency'],
    },
    {
      label: 'Estado',
      fields: ['active'],
    },
  ],
};
