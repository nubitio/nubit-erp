import { Field } from '@core/field/Field';
import { identityField, textField } from '@core/field/FieldBuilders';

export const PaymentMethodFields = () => {
  const fields: Field[] = [];

  fields.push(identityField().build());
  fields.push(textField().name('name').label('Nombre').sortable(true).build());

  return fields;
};
