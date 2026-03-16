import { Field } from '@platform/field/Field';
import { identityField, numberField, textField, switchField } from '@platform/field/FieldBuilders';

export const SunatCatalogFields = () => {
  const fields: Field[] = [];

  fields.push(identityField().build());
  fields.push(numberField().name('catalog').label('Catálogo').required(true).build());
  fields.push(textField().name('code').label('Código').required(true).build());
  fields.push(textField().name('description').label('Descripción').required(true).build());
  fields.push(numberField().name('percentage').label('Porcentaje').precision(2).build());
  fields.push(switchField().name('active').label('Activo').defaultValue(true).build());

  return fields;
};
