import { Field } from '@platform/field/Field';
import { identityField, textField } from '@platform/field/FieldBuilders';

export const PriceListFields = () => {
  const fields: Field[] = [];

  fields.push(identityField().valueType('number').build());
  fields.push(textField().name('name').label('Nombre').width('100%').required(true).build());
  return fields;
};
