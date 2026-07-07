import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();
import { Field } from '@nubitio/crud';
import { identityField, entityField, textField } from '@nubitio/crud';
import type { GridCellContext } from '@nubitio/crud';

export const CashRegisterFields = () => {
  const fields: Field[] = [];

  fields.push(identityField().build());
  fields.push(
    entityField(API + 'branches', 'id', 'name')
      .name('branch')
      .label('Sucursal')
      .filters([{ field: 'isActive', operator: '=', value: 1 }])
      .formatter((cell: GridCellContext) => (cell.value as { name?: string } | undefined)?.name)
      .required(true)
      .build(),
  );
  fields.push(textField().name('name').label('Nombre').required(true).build());

  return fields;
};
