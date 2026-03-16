import { Field } from '@platform/field/Field';
import { identityField, entityField, textField } from '@platform/field/FieldBuilders';
import { Constants } from '@platform/common/Constants';
import { ColumnCellTemplateData } from 'devextreme/ui/data_grid';

export const BranchFields = () => {
  const fields: Field[] = [];

  fields.push(identityField().label('Local id').build());
  fields.push(
    entityField(Constants.API_BASE_URL + 'companies', 'id', 'businessName')
      .name('company')
      .label('Empresa')
      .required(true)
      .formatter((cell: ColumnCellTemplateData) => cell.data.company?.businessName)
      .build(),
  );
  fields.push(textField().name('name').label('Descripcion').required(true).build());
  fields.push(textField().name('address').label('Direccion').build());
  fields.push(textField().name('phone').label('Telefono').build());

  return fields;
};
