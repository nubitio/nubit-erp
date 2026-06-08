import type { FormLayout } from '@nubit/crud';

export const customerFormLayout: FormLayout = {
  type: 'sections',
  sections: [
    {
      label: 'Identificación',
      fields: ['ruc', 'businessName', 'fiscalAddress'],
    },
    {
      label: 'Contacto y condiciones',
      fields: ['phone', 'email', 'priceList'],
    },
  ],
};