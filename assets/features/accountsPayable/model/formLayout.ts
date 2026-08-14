import type { FormLayout } from '@nubitio/crud';

export const supplierBillFormLayout: FormLayout = {
  type: 'sections',
  sections: [
    {
      label: 'Proveedor',
      fields: ['supplier', 'branch', 'category'],
    },
    {
      label: 'Documento',
      fields: ['documentType', 'series', 'number'],
    },
    {
      label: 'Montos y fechas',
      fields: ['amount', 'currency', 'issueDate', 'dueDate'],
    },
    {
      label: 'Notas',
      fields: ['note'],
    },
  ],
};
