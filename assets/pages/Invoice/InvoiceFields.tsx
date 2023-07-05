import {Field} from "../../core/field/Field";
import {FieldBuilder} from "../../core/field/FieldBuilder";
import {FieldType} from "../../core/field/FieldType";

export const InvoiceFields = () => {
    const fields: Field[] = [];

    fields.push(new FieldBuilder()
        .isIdentity(true)
        .visible(false)
        .type(FieldType.NONE)
        .name('comprobante_id')
        .label('Comprobante id')
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.DATE)
        .name('fecha')
        .label('Fecha')
        /*.formatter(SharedUtils.dateFormatter)*/
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('cliente_id')
        .label('DNI/RUC')
        /*.formatter((value: any, row: any) => row['cliente']?.['ruc'])*/
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('c.razon_social')
        .label('Cliente')
        /*.formatter((value: any, row: any) => row['cliente']?.['razon_social'])*/
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('tipo_comprobante_id')
        .label('Tipo')
        /*.formatter((value: any) => value === '01' ? 'Factura' : 'Boleta')*/
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('serie')
        .label('Serie')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('numero')
        .label('Número')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.CURRENCY)
        .name('total')
        .label('Total')
        .align('right')
        /*.formatter((value: number) => NumberUtils.formatCurrency(value))*/
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NONE)
        .name('nota')
        .label('Nota C.')
        .align('center')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.SWITCH)
        .name('voided')
        .label('Anulado')
        /*.formatter(SharedUtils.voidedFormatter)*/
        .align('center')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('ticket_baja')
        .label('Ticket baja')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('envio_sunat')
        .label('Envio sunat')
        /*.formatter(SharedUtils.billStatusFormatter)*/
        .align('center')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('mensaje_sunat')
        .label('Mensaje sunat')
        .build());

    return fields;
}
