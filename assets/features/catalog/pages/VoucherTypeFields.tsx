import { Field } from '@core/field/Field';
import { textField } from '@core/field/FieldBuilders';

export const VoucherTypeFields = () => {
  const fields: Field[] = [];

  fields.push(textField().isIdentity(true).name('code').label('Codigo').required(true).build());
  fields.push(textField().name('name').label('Descripcion').width('100%').required(true).build());

  return fields;
};
