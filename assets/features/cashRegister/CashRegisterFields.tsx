import { Constants } from '@core/common/Constants';
import { Field } from '@core/field/Field';
import { identityField, entityField, textField } from '@core/field/FieldBuilders';
import type { GridCellContext } from '@core/field/FieldCallbacks';

export const CashRegisterFields = () => {
  const fields: Field[] = [];

  fields.push(identityField().build());
  fields.push(
    entityField(Constants.API_BASE_URL + 'branches', 'id', 'name')
      .name('branch')
      .label('Sucursal')
      .formatter((cell: GridCellContext) => cell.value?.name)
      .required(true)
      .build(),
  );
  fields.push(textField().width('100%').name('name').label('Nombre').required(true).build());

  return fields;
};
