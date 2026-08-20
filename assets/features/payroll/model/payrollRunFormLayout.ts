import type { FormLayout } from '@nubitio/crud';

export const payrollRunFormLayout: FormLayout = {
  type: 'sections',
  sections: [
    {
      label: 'Corrida de planilla',
      fields: ['company', 'period', 'type'],
    },
  ],
};
