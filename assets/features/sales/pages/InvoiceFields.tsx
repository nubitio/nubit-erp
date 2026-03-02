import {Constants} from '@platform/common/Constants';
import {Field} from '@platform/field/Field';
import {FieldBuilder} from '@platform/field/FieldBuilder';
import {FieldType} from '@platform/field/FieldType';
import NumberUtils from '@shared/lib/NumberUtils';
import {SharedUtils} from '@shared/lib/SharedUtils';
import {ColumnCellTemplateData} from 'devextreme/ui/data_grid';

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
      .name('issueDate')
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
      .valueField('id')
      .textField('businessName')
      .formatter((cell: ColumnCellTemplateData) => cell.value?.businessName)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.ENUM)
      .name('documentType')
      .label('Tipo')
      .data([
        {value: '01', text: 'Factura'},
        {value: '03', text: 'Boleta'},
      ])
      .formatter((cell: ColumnCellTemplateData) =>
        cell.data.documentType === '01' ? 'Factura' : 'Boleta',
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
      .formatter((cell: ColumnCellTemplateData) => NumberUtils.formatCurrency(cell.value, cell.data?.currency ?? 'PEN'))
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
      .filterable(false)
      .sortable(false)
      .formatter((cell: ColumnCellTemplateData) => {
        if (cell.value === true) {
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

export const InvoiceDetailFields = (currency: string = 'PEN') => {
  const fields: Field[] = [];

  fields.push(
    new FieldBuilder()
      .isIdentity(true)
      .visible(false)
      .type(FieldType.NONE)
      .name('id')
      .label('Id')
      .required(true)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.TEXT)
      .name('productCode')
      .label('Código')
      .minWidth(120)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.TEXT)
      .name('productName')
      .label('Producto')
      .minWidth(260)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.TEXT)
      .name('productUom')
      .label('U.M.')
      .align('center')
      .minWidth(80)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.NUMBER)
      .name('quantity')
      .label('Cantidad')
      .align('center')
      .valueType('number')
      .precision(2)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.CURRENCY)
      .name('unitPrice')
      .label('Precio')
      .align('right')
      .formatter((cell: ColumnCellTemplateData) => NumberUtils.formatCurrency(cell.value, currency))
      .sendAsString(true)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.CURRENCY)
      .name('taxAmount')
      .label('IGV')
      .align('right')
      .formatter((cell: ColumnCellTemplateData) => NumberUtils.formatCurrency(cell.value, currency))
      .sendAsString(true)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.CURRENCY)
      .name('total')
      .label('Total')
      .align('right')
      .formatter((cell: ColumnCellTemplateData) => NumberUtils.formatCurrency(cell.value, currency))
      .sendAsString(true)
      .build(),
  );

  return fields;
};
