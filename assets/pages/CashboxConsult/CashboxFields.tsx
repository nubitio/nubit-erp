import {Field} from "../../core/field/Field";
import {FieldBuilder} from "../../core/field/FieldBuilder";
import {FieldType} from "../../core/field/FieldType";

export const CashboxConsultFields = () => {
    const fields: Field[] = [];

    fields.push(new FieldBuilder()
        .isIdentity(true)
        .visible(false)
        .type(FieldType.NONE)
        .name('caja_id')
        .label('Caja')
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('apertura')
        .label('Apertura')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('cierre')
        .label('Cierre')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NUMBER)
        .name('usuario')
        .label('Cajero')
        .width(100)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('saldo_inicial')
        .label('Saldo inicial')
        .align('right')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('saldo_final')
        .label('Saldo final')
        .align('right')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.SWITCH)
        .name('estado')
        .label('Estado')
        .align('center')
        /*.formatter(this.statusFormatter)*/
        .build());

    /*detailFields.push(new FieldBuilder()
        .type(FieldType.NONE)
        .name('tipo_id')
        .label('Tipo')
        .formatter(this.typeFormatter)
        .build());
    detailFields.push(new FieldBuilder()
        .type(FieldType.NONE)
        .name('descripcion')
        .label('Descripción')
        .build());
    detailFields.push(new FieldBuilder()
        .type(FieldType.NONE)
        .name('referencia')
        .label('Observación')
        .width(100)
        .build());
    detailFields.push(new FieldBuilder()
        .type(FieldType.NONE)
        .name('monto')
        .label('Monto')
        .align('right')
        .build());
    */
    return fields;
}
