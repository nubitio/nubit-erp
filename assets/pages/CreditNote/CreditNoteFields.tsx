import {Field} from "../../core/field/Field";
import {FieldBuilder} from "../../core/field/FieldBuilder";
import {FieldType} from "../../core/field/FieldType";
import FilterRule from "../../core/common/FilterRule";
import {Constants} from "../../core/common/Constants";
import NumberUtils from "../../core/utils/NumberUtils";
import {SharedUtils} from "../../core/utils/SharedUtils";

export const CreditNoteFields = () => {
    const fields: Field[] = [];

    fields.push(new FieldBuilder()
        .isIdentity(true)
        .visible(false)
        .type(FieldType.NONE)
        .name('nota_id')
        .label('Nota id')
        .required(true)
        .build());
    let saleFilter: FilterRule = {
        field: 'invoice',
        value: '',
        op: 'isnotnull'
    }
    fields.push(new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('sale')
        .label('Comprobante')
        .visible(false)
        .mode('remote')
        .url(Constants.API_BASE_URL + 'sales?filterRules=[' + JSON.stringify(saleFilter) + ']')
        .textField('invoice')
        .valueField('movimiento_id')
        /*.formatter((row: any) => `<b>C.P:</b> ${row.invoice ?? ''}<br><b>Total:</b> ${NumberUtils.formatCurrency(row.total)}`)*/
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.DATE)
        .name('fecha')
        .label('Fecha')
        /*.formatter(SharedUtils.dateFormatter)*/
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NONE)
        .name('tipo_id')
        .label('Tipo')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.SELECT)
        .name('codigo_motivo')
        .label('Código')
        .data(
            [{
                value: '01',
                text: 'Anulación de la operación'
            }, {
                value: '02',
                text: 'Anulación por error en el RUC'
            }, {
                value: '03',
                text: 'Corrección por error en la descripción'
            }, {
                value: '04',
                text: 'Descuento global'
            }, {
                value: '05',
                text: 'Descuento por ítem'
            }, {
                value: '06',
                text: 'Devolución total'
            }, {
                value: '07',
                text: 'Devolución por ítem'
            }, {
                value: '08',
                text: 'Bonificación'
            }, {
                value: '09',
                text: 'Disminución en el valor'
            }, {
                value: '10',
                text: 'Otros Conceptos'
            }]
        )
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXTAREA)
        .name('descripcion_motivo')
        .label('Motivo Descripción')
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NONE)
        .name('serie')
        .label('Serie')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NONE)
        .name('correlativo')
        .label('Número')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NONE)
        .name('sale')
        .label('Doc. afectado')
        .align('center')
        /*.formatter((value: any) => value?.invoice)*/
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NONE)
        .name('envio_sunat')
        .label('Envio sunat')
        .align('center')
        /*.formatter(SharedUtils.billStatusFormatter)*/
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NONE)
        .name('mensaje_sunat')
        .label('Mensaje sunat')
        .build());

    return fields;
}
