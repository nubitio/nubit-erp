import {Field} from '../../core/field/Field';
import {FieldBuilder} from '../../core/field/FieldBuilder';
import {FieldType} from '../../core/field/FieldType';
import {Constants} from '../../core/common/Constants';
import {ColumnCellTemplateData} from 'devextreme/ui/data_grid';
import {SharedUtils} from '../../core/utils/SharedUtils';
import NumberUtils from '../../core/utils/NumberUtils';

const reasonCodes: { value: string; text: string }[] = [
  {
    value: '01',
    text: 'Anulación de la operación',
  },
  {
    value: '02',
    text: 'Anulación por error en el RUC',
  },
  {
    value: '03',
    text: 'Corrección por error en la descripción',
  },
  {
    value: '04',
    text: 'Descuento global',
  },
  {
    value: '05',
    text: 'Descuento por ítem',
  },
  {
    value: '06',
    text: 'Devolución total',
  },
  {
    value: '07',
    text: 'Devolución por ítem',
  },
  {
    value: '08',
    text: 'Bonificación',
  },
  {
    value: '09',
    text: 'Disminución en el valor',
  },
  {
    value: '10',
    text: 'Otros Conceptos',
  },
]

export const CreditNoteFields = () => {
  const fields: Field[] = [];

  fields.push(
    new FieldBuilder()
      .isIdentity(true)
      .visible(false)
      .type(FieldType.NONE)
      .name('id')
      .label('ID')
      .required(true)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.ENTITY)
      .name('relatedTaxDocument')
      .label('Comprobante')
      .visible(false)
      .url(Constants.API_BASE_URL + 'tax-documents')
      .filters([
        {
          field: 'documentType',
          operator: '=',
          value: '01'
        }
      ])
      .textField('fullNumber')
      .valueField('id')
      .itemFormatter((data: any) => {
        return (
          <div>
            <b>{data.series + '-' + data.number ?? ''}</b>
            <br/>
            {data.customer?.businessName ?? ''}
            <br/>
            {NumberUtils.formatCurrency(data.total)}
          </div>
        );
      })
      .required(true)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.DATE)
      .name('issueDate')
      .label('Fecha')
      .formatter((cell: ColumnCellTemplateData) => SharedUtils.dateFormatter(cell.value))
      .defaultValue(new Date())
      .required(true)
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
      .visibleOnForm(false)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.NONE)
      .name('documentType')
      .label('Tipo')
      .formatter((cell: ColumnCellTemplateData) => {
        if (cell.value === '07') {
          return 'Nota de Crédito';
        } else if (cell.value === '08') {
          return 'Nota de Débito';
        } else {
          return cell.value;
        }
      })
      .align('center')
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.SELECT)
      .name('reasonCode')
      .label('Código')
      .data(reasonCodes)
      .formatter((cell: ColumnCellTemplateData) => {
        const reason = reasonCodes.find((r) => r.value === cell.value);
        return reason ? reason.value + ' - ' + reason.text : cell.value;
      })
      .required(true)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.TEXTAREA)
      .name('reasonDescription')
      .label('Motivo Descripción')
      .required(true)
      .build(),
  );
  fields.push(new FieldBuilder().type(FieldType.NONE).name('series').label('Serie').build());
  fields.push(new FieldBuilder().type(FieldType.NONE).name('number').label('Número').build());
  fields.push(
    new FieldBuilder()
      .type(FieldType.NONE)
      .name('affectedDocument')
      .label('Doc. afectado')
      .align('center')
      .formatter((cell: ColumnCellTemplateData) => cell.value ?? cell.data.sale?.invoice ?? '')
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.NONE)
      .name('sunatStatus')
      .label('Envio sunat')
      .align('center')
      .formatter((cell: ColumnCellTemplateData) => SharedUtils.billStatusFormatter(cell.value))
      .build(),
  );
  fields.push(
    new FieldBuilder().type(FieldType.NONE).name('sunatMessage').label('Mensaje sunat').build(),
  );

  return fields;
};

export const CreditNoteDetailFields = () => {
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
    new FieldBuilder()
      .type(FieldType.NUMBER)
      .name('lineNumber')
      .label('Linea')
      .visible(false)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.TEXT)
      .name('productCode')
      .label('Código')
      .minWidth(120)
      .readonly(true)
      .visible(false)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.TEXT)
      .name('productName')
      .label('Producto')
      .minWidth(260)
      .readonly(true)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.TEXT)
      .name('productUom')
      .label('U.M.')
      .align('center')
      .minWidth(80)
      .readonly(true)
      .visible(false)
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
      .onChange(function (this: any, row: any, value: any, currentRow: any) {
        if (value === '') return;
        row.total = Number(currentRow.unitPrice ?? 0) * Number(value ?? 0);
        this.defaultSetCellValue(row, value, currentRow);
      })
      .sendAsString(true)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.CURRENCY)
      .name('unitPrice')
      .label('Precio')
      .align('right')
      .formatter((cell: ColumnCellTemplateData) => NumberUtils.formatCurrency(cell.value))
      .readonly(true)
      .sendAsString(true)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.CURRENCY)
      .name('total')
      .label('Total')
      .align('right')
      .formatter((cell: ColumnCellTemplateData) => NumberUtils.formatCurrency(cell.value))
      .readonly(true)
      .sendAsString(true)
      .build(),
  );

  return fields;
};
