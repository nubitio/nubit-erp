import type { FormLayout } from '@nubit/crud';

export const customerFormLayout: FormLayout = {
  type: 'tabs',
  tabs: [
    {
      label: 'General',
      icon: 'ph-identification-card',
      fields: ['ruc', 'businessName', 'fiscalAddress'],
    },
    {
      label: 'Contacto',
      icon: 'ph-phone',
      fields: ['phone', 'email'],
    },
    {
      label: 'Comercial',
      icon: 'ph-tag',
      fields: ['priceList'],
    },
  ],
};