import { FieldBuilder } from '../../../../core/field/FieldBuilder';
import { FieldType } from '../../../../core/field/FieldType';
import { Field } from '../../../../core/field/Field';
import { ColumnCellTemplateData } from 'devextreme/ui/data_grid';
import { Button } from 'devextreme-react';
import NumberUtils from '../../../../shared/lib/NumberUtils';

const cashRegisterDashboardFields = (navigate: (path: string) => void) => {
  const fields: Field[] = [];

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
    new FieldBuilder().type(FieldType.TEXT).width('100%').name('name').label('Caja').build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.TEXT)
      .name('status')
      .label('Estado')
      .formatter((cell: ColumnCellTemplateData) => {
        if (cell.data.status === 'open') {
          return <span className="badge badge-outlined rounded-pill text-bg-success">Abierta</span>;
        } else {
          return (
            <span className="badge badge-outlined rounded-pill text-bg-secondary">Cerrada</span>
          );
        }
      })
      .build(),
  );
  fields.push(new FieldBuilder().type(FieldType.TEXT).name('cashier').label('Cajero').build());
  fields.push(
    new FieldBuilder()
      .type(FieldType.CURRENCY)
      .name('todaySales')
      .label('Ventas hoy')
      .align('right')
      .formatter((cell: ColumnCellTemplateData) =>
        cell.value != null ? NumberUtils.formatCurrency(Number(cell.value)) : '—',
      )
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.CURRENCY)
      .name('balance')
      .label('Saldo actual')
      .align('right')
      .formatter((cell: ColumnCellTemplateData) => NumberUtils.formatCurrency(cell.value))
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.TEXT)
      .name('-')
      .label('')
      .align('center')
      .filterable(false)
      .formatter((cell: ColumnCellTemplateData) => {
        if (cell.data.status === 'open') {
          return (
            <>
              <div style={{ display: 'flex', gap: '8px' }}>
                <Button
                  hint="Ver Movimientos"
                  type="default"
                  stylingMode="outlined"
                  icon="ph ph-eye"
                  onClick={() => {
                    navigate(`/cash-session/${cell.data.cashSessionId}/movements`);
                  }}
                />
                <Button
                  hint="Cerrar Caja"
                  type="danger"
                  stylingMode="outlined"
                  icon="ph ph-cash-register"
                  onClick={() => {
                    navigate(`/cash-register/${cell.data.cashSessionId}/close`);
                  }}
                />
              </div>
            </>
          );
        } else {
          return (
            <Button
              text="Abrir"
              type="success"
              stylingMode="outlined"
              icon="ph ph-cash-register"
              onClick={() => {
                navigate(`/cash-register/${cell.data.id}/open`);
              }}
            />
          );
        }
      })
      .build(),
  );

  return fields;
};

export default cashRegisterDashboardFields;
