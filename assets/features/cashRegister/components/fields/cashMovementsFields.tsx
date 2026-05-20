import { Field } from '../../../../core/field/Field';
import {
  identityField,
  entityField,
  currencyField,
  enumField,
  textareaField,
} from '../../../../core/field/FieldBuilders';
import type { GridCellContext } from '@nubit/core';
import NumberUtils from '../../../../shared/lib/NumberUtils';
import { Constants } from '../../../../core/common/Constants';

const cashMovementsFields = (cashShiftId: string | undefined) => {
  const fields: Field[] = [];

  fields.push(identityField().build());
  fields.push(
    entityField(Constants.API_BASE_URL + 'cash-sessions', 'id', 'id')
      .name('cashSession')
      .label('Sesión')
      .valueType('number')
      .defaultValue(Number(cashShiftId))
      .required(true)
      .visible(false)
      .hidden(true)
      .readonly(true)
      .build(),
  );
  fields.push(
    entityField(Constants.API_BASE_URL + 'cash-movement-categories', 'id', 'name')
      .name('category')
      .label('Categoría')
      .formatter((cell: GridCellContext) => cell.value?.name)
      .required(true)
      .build(),
  );
  fields.push(
    enumField([
      { value: 'income', text: 'Ingreso' },
      { value: 'expense', text: 'Egreso' },
    ])
      .name('type')
      .label('Tipo')
      .formatter((cell: GridCellContext) => {
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
  fields.push(textareaField().name('description').label('Descripcion').required(true).build());
  fields.push(
    currencyField()
      .name('amount')
      .label('Monto')
      .formatter((cell: GridCellContext) => NumberUtils.formatCurrency(cell.value))
      .required(true)
      .build(),
  );

  return fields;
};

export default cashMovementsFields;
