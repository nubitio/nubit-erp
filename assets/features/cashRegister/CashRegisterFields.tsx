import { getCoreApiBaseUrl } from '@nubit/core';

const API = getCoreApiBaseUrl();
import { Field } from '@nubit/crud';
import { identityField, entityField, textField } from '@nubit/crud';
import type { GridCellContext } from '@nubit/crud';

export const CashRegisterFields = () => {
  const fields: Field[] = [];

  fields.push(identityField().build());
  fields.push(
    entityField(API + 'branches', 'id', 'name')
      .name('branch')
      .label('Sucursal')
      .formatter((cell: GridCellContext) => (cell.value as { name?: string } | undefined)?.name)
      .required(true)
      .build(),
  );
  fields.push(textField().name('name').label('Nombre').required(true).build());

  return fields;
};
