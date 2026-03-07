import { FieldBuilder } from '../../../../core/field/FieldBuilder';
import { FieldType } from '../../../../core/field/FieldType';
import { Field } from '../../../../core/field/Field';
import { ColumnCellTemplateData } from 'devextreme/ui/data_grid';
import NumberUtils from '../../../../shared/lib/NumberUtils';
import { Constants } from '../../../../core/common/Constants';

const cashMovementsFields = (cashSessionId: string | undefined) => {
  const fields: Field[] = [];

  void cashSessionId; // cashSessionId is encoded in the endpoint URL; not needed as a form field

  fields.push(
    new FieldBuilder()
      .isIdentity(true)
      .visible(false)
      .type(FieldType.NONE)
      .name('id')
      .label('Id')
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.ENTITY)
      .name('category')
      .label('Categoría')
      .url(Constants.API_BASE_URL + 'cash-movement-categories')
      .valueField('id')
      .textField('name')
      .formatter((cell: ColumnCellTemplateData) => cell.value?.name)
      .required(false)
      .build(),
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
      .visibleOnForm(false)
      .required(true)
      .defaultValue('income')
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.TEXTAREA)
      .name('description')
      .label('Descripcion')
      .required(true)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.CURRENCY)
      .name('amount')
      .label('Monto')
      .align('right')
      .formatter((cell: ColumnCellTemplateData) => NumberUtils.formatCurrency(cell.value))
      .required(true)
      .sendAsString(true)
      .build(),
  );

  return fields;
};

export default cashMovementsFields;
