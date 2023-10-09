import {FieldBuilder} from "../../../../core/field/FieldBuilder";
import {FieldType} from "../../../../core/field/FieldType";
import {Constants} from "../../../../core/common/Constants";
import NumberUtils from "../../../../core/utils/NumberUtils";
import {Field} from "../../../../core/field/Field";
import {ColumnCellTemplateData} from "devextreme/ui/data_grid";

export const ConsultationSaleFields = (id: number, customerId: String) => {

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
        .isIdentity(true)
        .visible(false)
        .type(FieldType.NONE)
        .name('id')
        .label('Id')
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('sale')
        .label('Sale')
        .url(Constants.API_BASE_URL + 'customers/' + customerId + '/sales')
        .valueField('movimiento_id')
        .textField('numero')
        .valueType('string')
        .visible(false)
        .mode('remote')
        .itemFormatter((data: any) => {
            console.log('data', data);
            return (
                <div>
                    <b>Voucher: {data.serie}-{data.numero}</b><br/>
                    <b>C.P:</b> {data.invoice ?? ''}<br/>
                    <b>Total:</b> {NumberUtils.formatCurrency(data.total)}
                </div>
            )
        })
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NONE)
        .name('numero')
        .label('Voucher')
        .formatter((cell: ColumnCellTemplateData) => `${cell.data.sale.serie}-${cell.data.sale.numero}`)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NONE)
        .name('invoice')
        .label('Comprobante')
        .formatter((cell: ColumnCellTemplateData) => cell.data.sale.invoice)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NONE)
        .name('total')
        .label('Total')
        .align('right')
        .formatter((cell: ColumnCellTemplateData) => NumberUtils.formatCurrency(cell.data.sale.total))
        .build());

    return fields
}
