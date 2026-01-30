import { Field } from '../../core/field/Field';
import { FieldBuilder } from '../../core/field/FieldBuilder';
import { FieldType } from '../../core/field/FieldType';

export const SunatCatalogFields = () => {
  const fields: Field[] = [];

  fields.push(
    new FieldBuilder()
      .isIdentity(true)
      .visible(false)
      .type(FieldType.NONE)
      .name('id')
      .label('Id')
      .required(true)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.NUMBER)
      .name('catalog')
      .label('Catálogo')
      .required(true)
      .build(),
  );
  fields.push(
    new FieldBuilder().type(FieldType.TEXT).name('code').label('Código').required(true).build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.TEXT)
      .name('description')
      .label('Descripción')
      .required(true)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.NUMBER)
      .name('percentage')
      .label('Porcentaje')
      .precision(2)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.SWITCH)
      .name('active')
      .label('Activo')
      .defaultValue(true)
      .build(),
  );

  return fields;
};
