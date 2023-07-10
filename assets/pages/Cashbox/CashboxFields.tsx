import {Field} from "../../core/field/Field";
import {FieldBuilder} from "../../core/field/FieldBuilder";
import {FieldType} from "../../core/field/FieldType";
import NumberUtils from "../../core/utils/NumberUtils";

export const CashboxFields = () => {
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
        .visible(false)
        .type(FieldType.TEXT)
        .name('caja_id')
        .label('Caja')
        .hidden(true)
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENUM)
        .name('tipo_id')
        .label('Tipo')
        .data([
            {value: 1, text: 'INGRESO'},
            {value: 2, text: 'EGRESO'},
        ])
        /*.formatter(this.typeFormatter)*/
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('descripcion')
        .label('Concepto')
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXTAREA)
        .name('referencia')
        .label('Observaciones')
        .width(100)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.CURRENCY)
        .name('monto')
        .label('Monto')
        .align('right')
        .formatter((value: number) => NumberUtils.formatCurrency(value))
        .required(true)
        .build());

    return fields;
}
