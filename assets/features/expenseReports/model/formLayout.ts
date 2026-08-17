import type { FormLayout } from '@nubitio/crud';

export const expenseReportFormLayout: FormLayout = {
  type: 'sections',
  sections: [
    {
      label: 'Gasto',
      fields: ['employee', 'category', 'branch', 'description'],
    },
    {
      label: 'Monto y fecha',
      fields: ['amount', 'currency', 'expenseDate'],
    },
    {
      label: 'Comprobante',
      fields: ['receiptMedia'],
    },
    {
      label: 'Notas',
      fields: ['note'],
    },
  ],
};
