import {Field} from "../../core/field/Field";
import {FieldBuilder} from "../../core/field/FieldBuilder";
import {FieldType} from "../../core/field/FieldType";
import {ColumnCellTemplateData} from "devextreme/ui/data_grid";
import {SharedUtils} from "../../core/utils/SharedUtils";
import {Constants} from "../../core/common/Constants";
import NumberUtils from "../../core/utils/NumberUtils";

export const InboundFields = () => {
    const fields: Field[] = [];

    fields.push(new FieldBuilder()
        .isIdentity(true)
        .visible(false)
        .type(FieldType.NONE)
        .name('movimiento_id')
        .label('Movimiento id')
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NUMBER)
        .name('tipo_id')
        .label('Tipo id')
        .defaultValue(1)
        .hidden(true)
        .visible(false)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.DATE)
        .name('fecha')
        .label('Fecha')
        .defaultValue(new Date())
        .formatter((cell: ColumnCellTemplateData) => SharedUtils.dateFormatter(cell.value))
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('operacion')
        .label('Operacion')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('almacen')
        .label('Almacen')
        .url(Constants.API_BASE_URL + 'warehouses')
        .valueField('almacen_id')
        .textField('descripcion')
        .formatter((cell: ColumnCellTemplateData) => cell.value?.descripcion)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('proveedor')
        .label('Proveedor')
        .url(Constants.API_BASE_URL + 'suppliers')
        .valueField('proveedor_id')
        .textField('razon_social')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXTAREA)
        .name('glosa')
        .label('Glosa')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.CURRENCY)
        .name('total')
        .label('Total')
        .align('right')
        .formatter((cell: ColumnCellTemplateData) => NumberUtils.formatCurrency(cell.value))
        .build());

    return fields;
}

export const InboundDetailFields = () => {
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
        .type(FieldType.TEXT)
        .name('movimiento_id')
        .label('Movimiento id')
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('producto_id')
        .label('Producto id')
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('cantidad')
        .label('Cantidad')
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('precio_unitario')
        .label('Precio unitario')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('descuento')
        .label('Descuento')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('precio_total')
        .label('Precio total')
        .build());

    return fields;
}
