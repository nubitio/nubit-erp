import { Field } from '../../core/field/Field';
import { FieldBuilder } from '../../core/field/FieldBuilder';
import { FieldType } from '../../core/field/FieldType';
import { ColumnCellTemplateData } from 'devextreme/ui/data_grid';
import NumberUtils from '../../core/utils/NumberUtils';
import { SharedUtils } from '../../core/utils/SharedUtils';
import { Constants } from '../../core/common/Constants';

export const InvoiceFields = () => {
  const fields: Field[] = [];

  fields.push(
    new FieldBuilder()
      .isIdentity(true)
      .visible(false)
      .type(FieldType.NONE)
      .name('id')
      .label('Comprobante id')
      .required(true)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.DATE)
      .name('issuedAt')
      .label('Fecha')
      .valueType('date')
      .formatter((cell: ColumnCellTemplateData) => SharedUtils.dateFormatter(cell.value))
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.TEXT)
      .name('ruc')
      .label('DNI/RUC')
      .formatter((cell: ColumnCellTemplateData) => cell.data.customer?.ruc)
      .filterable(false)
      .sortable(false)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.ENTITY)
      .name('customer')
      .label('Cliente')
      .url(Constants.API_BASE_URL + 'customers')
      .valueField('ruc')
      .textField('businessName')
      .formatter((cell: ColumnCellTemplateData) => cell.value?.businessName)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.ENUM)
      .name('voucherTypeId')
      .label('Tipo')
      .data([
        { value: '01', text: 'Factura' },
        { value: '03', text: 'Boleta' },
      ])
      .formatter((cell: ColumnCellTemplateData) =>
        cell.data.voucherTypeId === '01' ? 'Factura' : 'Boleta',
      )
      .build(),
  );
  fields.push(new FieldBuilder().type(FieldType.TEXT).name('series').label('Serie').build());
  fields.push(new FieldBuilder().type(FieldType.TEXT).name('number').label('Número').build());
  fields.push(
    new FieldBuilder()
      .type(FieldType.CURRENCY)
      .name('total')
      .label('Total')
      .align('right')
      .formatter((cell: ColumnCellTemplateData) => NumberUtils.formatCurrency(cell.value))
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.NONE)
      .name('voidCorrelation')
      .label('Nota C.')
      .align('center')
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.SWITCH)
      .name('voided')
      .label('Anulado')
      .formatter((cell: ColumnCellTemplateData) => {
        if (cell.value === 2) {
          return <span className="badge badge-outlined rounded-pill text-bg-danger">Anulado</span>;
        }
        return '';
      })
      .align('center')
      .build(),
  );
  fields.push(
    new FieldBuilder().type(FieldType.TEXT).name('voidTicket').label('Ticket baja').build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.TEXT)
      .name('sunatStatus')
      .label('Envio sunat')
      .formatter((cell: ColumnCellTemplateData) => SharedUtils.billStatusFormatter(cell.value))
      .align('center')
      .build(),
  );
  fields.push(
    new FieldBuilder().type(FieldType.TEXT).name('sunatMessage').label('Mensaje sunat').build(),
  );

  return fields;
};
