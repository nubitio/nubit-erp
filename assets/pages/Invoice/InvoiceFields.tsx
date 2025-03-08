import {Field} from "../../core/field/Field";
import {FieldBuilder} from "../../core/field/FieldBuilder";
import {FieldType} from "../../core/field/FieldType";
import {ColumnCellTemplateData} from "devextreme/ui/data_grid";
import NumberUtils from "../../core/utils/NumberUtils";
import {SharedUtils} from "../../core/utils/SharedUtils";
import {Constants} from "../../core/common/Constants";

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
        .valueType('date')
        .formatter((cell: ColumnCellTemplateData) => SharedUtils.dateFormatter(cell.value))
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('ruc')
        .label('DNI/RUC')
        .formatter((cell: ColumnCellTemplateData) => cell.data.cliente?.ruc)
        .filterable(false)
        .sortable(false)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('cliente')
        .label('Cliente')
        .url(Constants.API_BASE_URL + 'customers')
        .valueField('ruc')
        .textField('businessName')
        .formatter((cell: ColumnCellTemplateData) => cell.value?.businessName)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENUM)
        .name('tipo_comprobante_id')
        .label('Tipo')
        .data([
            {value: '01', text: 'Factura'},
            {value: '03', text: 'Boleta'}
        ])
        .formatter((cell: ColumnCellTemplateData) => cell.data.tipo_comprobante_id === '01' ? 'Factura' : 'Boleta')
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
        .formatter((cell: ColumnCellTemplateData) => {
            if (cell.value === 2) {
                return <span className="badge badge-outlined rounded-pill text-bg-danger">Anulado</span>
            }
            return ''
        })
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
        .formatter((cell: ColumnCellTemplateData) => {
            let value = cell.value;
            if (value === null || value === undefined || value === '') {
                value = -1
            } else {
                value = Number(value);
            }
            if (value === -1) {
                return <span className="badge badge-outlined rounded-pill text-bg-primary">PENDIENTE</span>
            } else if (value === 0) {
                return <span className="badge badge-outlined rounded-pill text-bg-success">ENVIADO</span>
            } else if (value < 2000) {
                return <span className="badge badge-outlined rounded-pill text-bg-warning">ERRONEO</span>
            } else if (value >= 2000 && value < 4000) {
                return <span className="badge badge-outlined rounded-pill text-bg-danger">RECHAZADO</span>
            } else if (value >= 4000) {
                return <span className="badge badge-outlined rounded-pill text-bg-info">OBSERVADO</span>
            }
        })
        .align('center')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('mensaje_sunat')
        .label('Mensaje sunat')
        .build());

    return fields;
}
