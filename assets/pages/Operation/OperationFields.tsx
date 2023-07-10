import {Field} from "../../core/field/Field";
import {FieldBuilder} from "../../core/field/FieldBuilder";
import {FieldType} from "../../core/field/FieldType";
import {SharedUtils} from "../../core/utils/SharedUtils";

export const OperationFields = () => {
    const fields: Field[] = [];

    fields.push(new FieldBuilder()
        .isIdentity(true)
        .visible(false)
        .type(FieldType.NONE)
        .name('operacion_id')
        .label('Operacion id')
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('descripcion')
        .label('Descripcion')
        .width(100)
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENUM)
        .name('tipo_id')
        .label('Tipo')
        .data([
            {value: 1, text: 'Entrada'},
            {value: 2, text: 'Salida'},
        ])
        .formatter((value: number) => value === 1 ? 'Entrada' : 'Salida')
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.SWITCH)
        .name('estado')
        .label('Activo')
        .formatter(SharedUtils.dxBooleanFormatter)
        .defaultValue(true)
        .build());

    return fields;
}
