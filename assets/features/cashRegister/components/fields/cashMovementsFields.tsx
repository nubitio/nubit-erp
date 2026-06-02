import {
  type Field,
  identityField,
  entityField,
  currencyField,
  enumField,
  textareaField,
  type GridCellContext,
} from '@nubit/crud';
import { Badge } from '@nubit/ui';
import NumberUtils from '../../../../shared/lib/NumberUtils';
import { getCoreApiBaseUrl } from '@nubit/core';

const API = getCoreApiBaseUrl();

const cashMovementsFields = (cashShiftId: string | undefined) => {
  const fields: Field[] = [];

  fields.push(identityField().build());
  fields.push(
    entityField(API + 'cash-sessions', 'id', 'id')
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
    entityField(API + 'cash-movement-categories', 'id', 'name')
      .name('category')
      .label('Categoría')
      .formatter((cell: GridCellContext) => (cell.value as { name?: string } | undefined)?.name)
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
          return <Badge variant="success" outlined>Ingreso</Badge>;
        } else if (cell.value === 'expense') {
          return <Badge variant="danger" outlined>Egreso</Badge>;
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
      .formatter((cell: GridCellContext) => NumberUtils.formatCurrency(cell.value as number))
      .required(true)
      .build(),
  );

  return fields;
};

export default cashMovementsFields;
