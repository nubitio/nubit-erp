import { Field } from '../../core/field/Field';
import { FieldBuilder } from '../../core/field/FieldBuilder';
import { FieldType } from '../../core/field/FieldType';
import { ColumnCellTemplateData } from 'devextreme/ui/data_grid';
import { SharedUtils } from '../../core/utils/SharedUtils';

export const CashMovementCategoryFields = () => {
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
    new FieldBuilder().type(FieldType.TEXT).name('code').label('Código').required(true).build(),
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
        { value: 'income', text: 'Ingreso' },
        { value: 'expense', text: 'Egreso' },
      ])
      .formatter((cell: ColumnCellTemplateData) => {
        if (cell.value === 'income') {
          return <span className="badge badge-outlined rounded-pill text-bg-success">Ingreso</span>;
        } else if (cell.value === 'expense') {
          return <span className="badge badge-outlined rounded-pill text-bg-danger">Egreso</span>;
        }
      })
      .required(true)
      .build(),
  );

  fields.push(
    new FieldBuilder()
      .type(FieldType.SWITCH)
      .name('isSystem')
      .label('Sistema')
      .formatter(SharedUtils.booleanFormatter)
      .defaultValue(true)
      .build(),
  );

  fields.push(
    new FieldBuilder()
      .type(FieldType.SWITCH)
      .name('active')
      .label('Activo')
      .formatter(SharedUtils.booleanFormatter)
      .defaultValue(true)
      .build(),
  );

  return fields;
};
