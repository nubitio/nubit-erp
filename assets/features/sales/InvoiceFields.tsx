import { Constants } from '@core/common/Constants';
import { Field } from '@core/field/Field';
import {
  identityField,
  entityField,
  currencyField,
  dateField,
  enumField,
  numberField,
  textField,
  noneField,
  switchField,
} from '@core/field/FieldBuilders';
import NumberUtils from '@shared/lib/NumberUtils';
import { SharedUtils } from '@shared/lib/SharedUtils';
import type { GridCellContext } from '@core/field/FieldCallbacks';

export const InvoiceFields = () => {
  const fields: Field[] = [];

  fields.push(identityField().label('Comprobante id').build());
  fields.push(
    dateField()
      .name('issueDate')
      .label('Fecha')
      .defaultValue(undefined)
      .valueType('date')
      .formatter((cell: GridCellContext) => SharedUtils.dateFormatter(cell.value))
      .build(),
  );
  fields.push(
    textField()
      .name('ruc')
      .label('DNI/RUC')
      .formatter((cell: GridCellContext) => cell.data?.customer?.ruc)
      .filterable(false)
      .sortable(false)
      .build(),
  );
  fields.push(
    entityField(Constants.API_BASE_URL + 'customers', 'id', 'businessName')
      .name('customer')
      .label('Cliente')
      .formatter((cell: GridCellContext) => cell.value?.businessName)
      .build(),
  );
  fields.push(
    enumField([
      { value: '01', text: 'Factura' },
      { value: '03', text: 'Boleta' },
    ])
      .name('documentType')
      .label('Tipo')
      .formatter((cell: GridCellContext) =>
        cell.data.documentType === '01' ? 'Factura' : 'Boleta',
      )
      .build(),
  );
  fields.push(textField().name('series').label('Serie').build());
  fields.push(textField().name('number').label('Número').build());
  fields.push(
    currencyField()
      .name('total')
      .label('Total')
      .formatter((cell: GridCellContext) =>
        NumberUtils.formatCurrency(cell.value, cell.data?.currency ?? 'PEN'),
      )
      .build(),
  );
  fields.push(noneField().name('voidCorrelation').label('Nota C.').align('center').build());
  fields.push(
    switchField()
      .name('voided')
      .label('Anulado')
      .filterable(false)
      .sortable(false)
      .formatter((cell: GridCellContext) => {
        if (cell.value === true) {
          return <span className="badge badge-outlined rounded-pill text-bg-danger">Anulado</span>;
        }
        return '';
      })
      .align('center')
      .build(),
  );
  fields.push(textField().name('voidTicket').label('Ticket baja').build());
  fields.push(
    textField()
      .name('sunatStatus')
      .label('Envio sunat')
      .formatter((cell: GridCellContext) => SharedUtils.billStatusFormatter(cell.value))
      .align('center')
      .build(),
  );
  fields.push(textField().name('sunatMessage').label('Mensaje sunat').build());
  fields.push(
    textField()
      .name('detraccionCodigo')
      .label('Cód. Detrac.')
      .align('center')
      .formatter((cell: GridCellContext) => {
        if (!cell.value) return '';
        return <span className="badge text-bg-info">{cell.value}</span>;
      })
      .build(),
  );
  fields.push(
    currencyField()
      .name('detraccionMonto')
      .label('Detracción')
      .formatter((cell: GridCellContext) => {
        if (cell.value == null || cell.value === 0) return '';
        return (
          <span className="badge text-bg-warning">{`S/ ${Number(cell.value).toFixed(2)}`}</span>
        );
      })
      .build(),
  );

  return fields;
};

export const InvoiceDetailFields = (currency: string = 'PEN') => {
  const fields: Field[] = [];

  fields.push(identityField().build());
  fields.push(textField().name('productCode').label('Código').minWidth(120).build());
  fields.push(textField().name('productName').label('Producto').minWidth(260).build());
  fields.push(textField().name('productUom').label('U.M.').align('center').minWidth(80).build());
  fields.push(
    numberField()
      .name('quantity')
      .label('Cantidad')
      .align('center')
      .valueType('number')
      .precision(2)
      .build(),
  );
  fields.push(
    currencyField()
      .name('unitPrice')
      .label('Precio')
      .formatter((cell: GridCellContext) => NumberUtils.formatCurrency(cell.value, currency))
      .build(),
  );
  fields.push(
    currencyField()
      .name('taxAmount')
      .label('IGV')
      .formatter((cell: GridCellContext) => NumberUtils.formatCurrency(cell.value, currency))
      .build(),
  );
  fields.push(
    currencyField()
      .name('total')
      .label('Total')
      .formatter((cell: GridCellContext) => NumberUtils.formatCurrency(cell.value, currency))
      .build(),
  );

  return fields;
};
