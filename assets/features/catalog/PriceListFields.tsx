import { identityField, textField, type Field } from '@nubitio/crud';

export const PriceListFields = () => {
  const fields: Field[] = [];

  fields.push(identityField().valueType('number').build());
  fields.push(textField().name('name').label('Nombre').required(true).build());
  return fields;
};
