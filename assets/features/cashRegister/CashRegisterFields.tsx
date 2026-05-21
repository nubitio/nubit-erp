import { API_BASE_URL } from '@app/constants';
import { Field } from '@nubit/crud';
import { identityField, entityField, textField } from '@nubit/crud';
import type { GridCellContext } from '@nubit/crud';

export const CashRegisterFields = () => {
  const fields: Field[] = [];

  fields.push(identityField().build());
  fields.push(
    entityField(API_BASE_URL + 'branches', 'id', 'name')
      .name('branch')
      .label('Sucursal')
      .formatter((cell: GridCellContext) => cell.value?.name)
      .required(true)
      .build(),
  );
  fields.push(textField().width('100%').name('name').label('Nombre').required(true).build());

  return fields;
};
