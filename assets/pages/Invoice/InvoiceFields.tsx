import {Field} from "../../core/field/Field";
import {FieldBuilder} from "../../core/field/FieldBuilder";
import {FieldType} from "../../core/field/FieldType";
import {ColumnCellTemplateData} from "devextreme/ui/data_grid";
import NumberUtils from "../../core/utils/NumberUtils";
import {SharedUtils} from "../../core/utils/SharedUtils";

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
        .formatter((cell: ColumnCellTemplateData) => SharedUtils.dateFormatter(cell.value))
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('cliente_id')
        .label('DNI/RUC')
        .formatter((cell: ColumnCellTemplateData) => cell.data.cliente?.ruc)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('c.razon_social')
        .label('Cliente')
        .formatter((cell: ColumnCellTemplateData) => cell.data.cliente?.razon_social)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('tipo_comprobante_id')
        .label('Tipo')
        .formatter((cell: ColumnCellTemplateData) => cell.data.cliente?.tipo_comprobante_id === '01' ? 'Factura' : 'Boleta')
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
        .formatter((cell: ColumnCellTemplateData) => NumberUtils.formatCurrency(cell.value))
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
        //.formatter(SharedUtils.dxVoidedFormatter)
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
        //.formatter(SharedUtils.dxBillStatusFormatter)
        .align('center')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('mensaje_sunat')
        .label('Mensaje sunat')
        .build());

    return fields;
}
