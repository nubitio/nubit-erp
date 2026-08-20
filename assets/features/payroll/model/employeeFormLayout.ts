import type { FormLayout } from '@nubitio/crud';

export const employeeFormLayout: FormLayout = {
  type: 'sections',
  sections: [
    {
      label: 'Datos personales',
      fields: ['company', 'dni', 'firstName', 'lastName'],
    },
    {
      label: 'Contrato',
      fields: ['hireDate', 'terminationDate', 'contractType', 'baseSalary'],
    },
    {
      label: 'Pensión',
      fields: ['pensionScheme', 'afpProvider', 'epsAffiliated'],
    },
    {
      label: 'Acceso al sistema',
      fields: ['user'],
    },
  ],
};
