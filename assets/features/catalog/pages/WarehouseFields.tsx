import { Field } from '@platform/field/Field';
import { identityField, entityField, textField } from '@platform/field/FieldBuilders';
import { Constants } from '@platform/common/Constants';
import { ColumnCellTemplateData } from 'devextreme/ui/data_grid';

export const WarehouseFields = () => {
  const fields: Field[] = [];

  fields.push(identityField().build());
  fields.push(
    entityField(Constants.API_BASE_URL + 'branches', 'id', 'name')
      .name('branch')
      .label('Local')
      .required(true)
      .formatter((cell: ColumnCellTemplateData) => cell.data.branch.name)
      .build(),
  );
  fields.push(textField().name('name').label('Descripcion').required(true).build());

  return fields;
};
