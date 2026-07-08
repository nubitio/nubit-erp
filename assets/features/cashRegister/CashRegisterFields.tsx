import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();
import { Field } from '@nubitio/crud';
import { identityField, textField, switchField } from '@nubitio/crud';
import type { GridCellContext } from '@nubitio/crud';
import { activeEntityField } from '@shared/lib/activeEntityField';

export const CashRegisterFields = () => {
  const fields: Field[] = [];

  fields.push(identityField().build());
  fields.push(
    activeEntityField(API + 'branches', 'id', 'name')
      .name('branch')
      .label('Sucursal')
      .formatter((cell: GridCellContext) => (cell.value as { name?: string } | undefined)?.name)
      .required(true)
      .build(),
  );
  fields.push(textField().name('name').label('Nombre').required(true).build());
  fields.push(
    switchField().name('isActive').label('Activo').align('center').defaultValue(true).build(),
  );

  return fields;
};
