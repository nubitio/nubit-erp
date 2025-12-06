import { FieldBuilder } from '../../core/field/FieldBuilder';
import { FieldType } from '../../core/field/FieldType';
import { Field } from '../../core/field/Field';

export const ConfigFields = () => {
  const fields: Field[] = [];

  fields.push(
    new FieldBuilder()
      .isIdentity(true)
      .visible(false)
      .type(FieldType.NONE)
      .name('id')
      .label('Id')
      .required(true)
      .valueType('number')
      .build(),
  );

  fields.push(
    new FieldBuilder().type(FieldType.TEXT).name('name').label('Nombre').required(true).build(),
  );

  fields.push(
    new FieldBuilder()
      .type(FieldType.ENUM)
      .name('type')
      .label('Tipo')
      .data([
        { value: 'string', text: 'Texto' },
        { value: 'int', text: 'Entero' },
        { value: 'float', text: 'Decimal' },
        { value: 'bool', text: 'Booleano' },
        { value: 'json', text: 'JSON' },
      ])
      .valueField('value')
      .textField('text')
      .valueType('string')
      .required(true)
      .build(),
  );

  fields.push(
    new FieldBuilder().type(FieldType.TEXT).name('value').label('Valor').required(true).build(),
  );

  return fields;
};
