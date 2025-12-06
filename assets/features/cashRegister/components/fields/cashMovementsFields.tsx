import { FieldBuilder } from '../../../../core/field/FieldBuilder';
import { FieldType } from '../../../../core/field/FieldType';
import { Field } from '../../../../core/field/Field';
import { ColumnCellTemplateData } from 'devextreme/ui/data_grid';
import { useNavigate } from 'react-router-dom';
import NumberUtils from '../../../../core/utils/NumberUtils';
import { Constants } from '../../../../core/common/Constants';

const cashMovementsFields = (cashShiftId: string | undefined) => {
  const navigate = useNavigate();
  let fields: Field[] = [];

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
      .name('cashShift')
      .label('Turno')
      .url(Constants.API_BASE_URL + 'cash-shifts')
      .valueField('id')
      .textField('id')
      .valueType('number')
      .defaultValue(Number(cashShiftId))
      .required(true)
      .visible(false)
      .hidden(true)
      .readonly(true)
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
      .required(true)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.ENTITY)
      .name('paymentMethod')
      .label('Método de pago')
      .url(Constants.API_BASE_URL + 'payment-methods')
      .valueField('id')
      .textField('name')
      .valueType('number')
      .defaultValue(1)
      .required(true)
      .formatter((cell: ColumnCellTemplateData) => cell.value?.name)
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
      .build(),
  );

  return fields;
};

export default cashMovementsFields;
