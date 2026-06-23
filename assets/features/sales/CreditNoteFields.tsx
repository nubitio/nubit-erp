import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();
import { Field } from '@nubitio/crud';
import {
  identityField,
  entityField,
  currencyField,
  dateField,
  numberField,
  noneField,
  selectField,
  textareaField,
  textField,
} from '@nubitio/crud';
import NumberUtils from '@shared/lib/NumberUtils';

type TaxDocumentItem = {
  series?: string;
  number?: string;
  customer?: { businessName?: string };
  total?: number;
  currency?: string;
};

type CreditNoteDetailRow = DataRecord & {
  quantity?: number | string;
  unitPrice?: number;
  total?: number;
};

type FieldChangeContext = {
  defaultSetCellValue: (row: CreditNoteDetailRow, value: unknown, currentRow: CreditNoteDetailRow) => void;
};
import { SharedUtils } from '@shared/lib/SharedUtils';
import type { GridCellContext } from '@nubitio/crud';

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
  {
    value: '11',
    text: 'Ajustes de operaciones de exportación',
  },
  {
    value: '12',
    text: 'Ajustes afectos al IVAP',
  },
];

export const CreditNoteFields = () => {
  const fields: Field[] = [];

  fields.push(identityField().label('ID').build());
  fields.push(
    entityField(API + 'tax-documents', 'id', 'fullNumber')
      .name('relatedTaxDocument')
      .label('Comprobante')
      .visible(false)
      .filters([
        {
          field: 'documentType',
          operator: '=',
          value: '01',
        },
      ])
      .itemFormatter((data: TaxDocumentItem) => {
        return (
          <div>
            <b>{`${data.series ?? ''}-${data.number ?? ''}`}</b>
            <br />
            {data.customer?.businessName ?? ''}
            <br />
            {NumberUtils.formatCurrency(data.total, data.currency ?? 'PEN')}
          </div>
        );
      })
      .required(true)
      .build(),
  );
  fields.push(
    dateField()
      .name('issueDate')
      .label('Fecha')
      .formatter((cell: GridCellContext) => SharedUtils.dateFormatter(cell.value as string | Date | null | undefined))
      .required(true)
      .build(),
  );
  fields.push(
    entityField(API + 'customers', 'id', 'businessName')
      .name('customer')
      .label('Cliente')
      .formatter((cell: GridCellContext) => (cell.value as { businessName?: string } | undefined)?.businessName)
      .visibleOnForm(false)
      .build(),
  );
  fields.push(
    noneField()
      .name('documentType')
      .label('Tipo')
      .formatter((cell: GridCellContext) => {
        if (cell.value === '07') {
          return 'Nota de Crédito';
        } else if (cell.value === '08') {
          return 'Nota de Débito';
        } else {
          return cell.value as React.ReactNode;
        }
      })
      .align('center')
      .build(),
  );
  fields.push(
    selectField()
      .name('reasonCode')
      .label('Código')
      .data(reasonCodes)
      .formatter((cell: GridCellContext) => {
        const reason = reasonCodes.find((r) => r.value === cell.value);
        return reason ? reason.value + ' - ' + reason.text : String(cell.value ?? '');
      })
      .required(true)
      .build(),
  );
  fields.push(
    textareaField().name('reasonDescription').label('Motivo Descripción').required(true).build(),
  );
  fields.push(noneField().name('series').label('Serie').build());
  fields.push(noneField().name('number').label('Número').build());
  fields.push(
    noneField()
      .name('affectedDocument')
      .label('Doc. afectado')
      .align('center')
      .formatter((cell: GridCellContext) => (cell.value ?? (cell.data.sale as { invoice?: string } | undefined)?.invoice ?? '') as React.ReactNode)
      .build(),
  );
  fields.push(
    noneField()
      .name('sunatStatus')
      .label('Envio sunat')
      .align('center')
      .formatter((cell: GridCellContext) => SharedUtils.billStatusFormatter(cell.value as string | number | null | undefined))
      .build(),
  );
  fields.push(noneField().name('sunatMessage').label('Mensaje sunat').build());

  return fields;
};

export const CreditNoteDetailFields = (currency: string = 'PEN') => {
  const fields: Field[] = [];

  fields.push(identityField().build());
  fields.push(numberField().name('lineNumber').label('Linea').visible(false).build());
  fields.push(
    textField()
      .name('productCode')
      .label('Código')
      .minWidth(120)
      .readonly(true)
      .visible(false)
      .build(),
  );
  fields.push(
    textField().name('productName').label('Producto').minWidth(260).readonly(true).build(),
  );
  fields.push(
    textField()
      .name('productUom')
      .label('U.M.')
      .align('center')
      .minWidth(80)
      .readonly(true)
      .visible(false)
      .build(),
  );
  fields.push(
    numberField()
      .name('quantity')
      .label('Cantidad')
      .align('center')
      .valueType('number')
      .precision(2)
      .onChange(function (this: FieldChangeContext, row: CreditNoteDetailRow, value: number | string, currentRow: CreditNoteDetailRow) {
        if (value === '') return;
        row.total = Number(currentRow.unitPrice ?? 0) * Number(value ?? 0);
        this.defaultSetCellValue(row, value, currentRow);
      })
      .sendAsString(true)
      .build(),
  );
  fields.push(
    currencyField()
      .name('unitPrice')
      .label('Precio')
      .formatter((cell: GridCellContext) => NumberUtils.formatCurrency(cell.value as number, currency))
      .readonly(true)
      .build(),
  );
  fields.push(
    currencyField()
      .name('total')
      .label('Total')
      .formatter((cell: GridCellContext) => NumberUtils.formatCurrency(cell.value as number, currency))
      .readonly(true)
      .build(),
  );

  return fields;
};
