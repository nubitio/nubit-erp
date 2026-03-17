import { Field } from '@core/field/Field';
import { identityField, enumField, textField, switchField } from '@core/field/FieldBuilders';
import { ColumnCellTemplateData } from 'devextreme/ui/data_grid';
import { SharedUtils } from '@shared/lib/SharedUtils';

export const CashMovementCategoryFields = () => {
  const fields: Field[] = [];

  fields.push(identityField().valueType('number').build());

  fields.push(textField().name('code').label('Código').required(true).build());

  fields.push(textField().name('name').label('Nombre').required(true).build());

  fields.push(
    enumField([
      { value: 'income', text: 'Ingreso' },
      { value: 'expense', text: 'Egreso' },
    ])
      .name('type')
      .label('Tipo')
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
    switchField()
      .name('isSystem')
      .label('Sistema')
      .formatter((cell: ColumnCellTemplateData) => SharedUtils.booleanFormatter(cell.value))
      .defaultValue(true)
      .build(),
  );

  fields.push(
    switchField()
      .name('active')
      .label('Activo')
      .formatter((cell: ColumnCellTemplateData) => SharedUtils.booleanFormatter(cell.value))
      .defaultValue(true)
      .build(),
  );

  return fields;
};
