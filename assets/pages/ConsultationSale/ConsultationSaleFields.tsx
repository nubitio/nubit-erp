import {FieldBuilder} from "../../core/field/FieldBuilder";
import {FieldType} from "../../core/field/FieldType";
import {Constants} from "../../core/common/Constants";
import NumberUtils from "../../core/utils/NumberUtils";
import {SharedUtils} from "../../core/utils/SharedUtils";
import {Field} from "../../core/field/Field";
import {ColumnCellTemplateData} from "devextreme/ui/data_grid";

export  const ConsultationSaleFields = (id:number) =>{

    const fields: Field[] = [];

    fields.push(new FieldBuilder()
        .isIdentity(true)
        .visible(false)
        .type(FieldType.NONE)
        .name('id')
        .label('Id')
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('consultation')
        .label('Consultation')
        .url(Constants.API_BASE_URL + 'consultations')
        .valueField('id')
        .textField('id')
        .valueType('number')
        .defaultValue(id)
        .visible(false)
        .hidden(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('sale')
        .label('Sale')
        .url(Constants.API_BASE_URL + 'customers/29673719/sales')
        .valueField('movimiento_id')
        .textField('numero')
        //.formatter((row: any) => `<b>Voucher: ${row.serie}-${row.numero}</b><br><b>C.P:</b> ${row.invoice??''}<br><b>Total:</b> ${NumberUtils.formatCurrency(row.total)}`)
        .formatter((cell: ColumnCellTemplateData) => console.log(cell))
        .valueType('string')
        .visible(false)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NONE)
        .name('numero')
        .label('Voucher')
        .width(100)
        .formatter((value: any, row: any) => SharedUtils.voucherFormatter(row.sale, row.sale))
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NONE)
        .name('invoice')
        .label('Comprobante')
        .formatter((value: any, row: any) => SharedUtils.invoiceFormatter(row.sale.invoice, row.sale))
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NONE)
        .name('total')
        .label('Total')
        .align('right')
        .formatter((value: any, row: any) => NumberUtils.formatCurrency(row.sale.total))
        .build());fields.push(new FieldBuilder()
        .isIdentity(true)
        .visible(false)
        .type(FieldType.NONE)
        .name('id')
        .label('Id')
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('consultation')
        .label('Consultation')
        .url(Constants.API_BASE_URL + 'consultations')
        .valueField('id')
        .textField('id')
        .valueType('number')
        .visible(false)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('sale')
        .label('Sale')
        .url(Constants.API_BASE_URL + 'sales')
        .valueField('movimiento_id')
        .textField('numero')
        .formatter((row: any) => `<b>Voucher: ${row.serie}-${row.numero}</b><br><b>C.P:</b> ${row.invoice??''}<br><b>Total:</b> ${NumberUtils.formatCurrency(row.total)}`)
        .valueType('string')
        .visible(false)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NONE)
        .name('numero')
        .label('Voucher')
        .width(100)
        .formatter((value: any, row: any) => SharedUtils.voucherFormatter(row.sale, row.sale))
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NONE)
        .name('invoice')
        .label('Comprobante')
        .formatter((value: any, row: any) => SharedUtils.invoiceFormatter(row.sale.invoice, row.sale))
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NONE)
        .name('total')
        .label('Total')
        .align('right')
        .formatter((value: any, row: any) => NumberUtils.formatCurrency(row.sale.total))
        .build());

    return fields
}
