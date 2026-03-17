import { Field } from '@core/field/Field';
import { identityField, enumField, textField } from '@core/field/FieldBuilders';

export const ConfigFields = () => {
  const fields: Field[] = [];

  fields.push(identityField().valueType('number').build());

  fields.push(textField().name('name').label('Nombre').required(true).build());

  fields.push(
    enumField([
      { value: 'string', text: 'Texto' },
      { value: 'int', text: 'Entero' },
      { value: 'float', text: 'Decimal' },
      { value: 'bool', text: 'Booleano' },
      { value: 'json', text: 'JSON' },
    ])
      .name('type')
      .label('Tipo')
      .valueField('value')
      .textField('text')
      .valueType('string')
      .required(true)
      .build(),
  );

  fields.push(textField().name('value').label('Valor').required(true).build());

  return fields;
};
