import {Field} from "../../core/field/Field";
import {FieldBuilder} from "../../core/field/FieldBuilder";
import {FieldType} from "../../core/field/FieldType";

export const OutboundFields = () => {
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
        .type(FieldType.TEXT)
        .name('operacion_id')
        .label('Operacion id')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('almacen_id')
        .label('Almacen id')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('cliente_id')
        .label('Cliente id')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NUMBER)
        .name('tipo_id')
        .label('Tipo id')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.DATE)
        .name('fecha')
        .label('Fecha')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('proveedor_id')
        .label('Proveedor id')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NUMBER)
        .name('forma_pago')
        .label('Forma pago')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NUMBER)
        .name('tipo_pago')
        .label('Tipo pago')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NUMBER)
        .name('tipo_tarjeta')
        .label('Tipo tarjeta')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('serie')
        .label('Serie')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('numero')
        .label('Numero')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('descuento')
        .label('Descuento')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('glosa')
        .label('Glosa')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NUMBER)
        .name('orden_id')
        .label('Orden id')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.SWITCH)
        .name('has_invoice')
        .label('Has invoice')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.SWITCH)
        .name('is_consume')
        .label('Is consume')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('caja_id')
        .label('Caja id')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.SWITCH)
        .name('estado')
        .label('Estado')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NUMBER)
        .name('igv')
        .label('Igv')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NUMBER)
        .name('recargo_consumo')
        .label('Recargo consumo')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('invoice')
        .label('Invoice')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('subtotal')
        .label('Subtotal')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('total')
        .label('Total')
        .build());

    return fields;
}

export const OutboundDetailFields = () => {
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
