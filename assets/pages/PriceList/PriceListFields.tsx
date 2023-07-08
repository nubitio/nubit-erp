import {Field} from "../../core/field/Field";
import {FieldBuilder} from "../../core/field/FieldBuilder";
import {FieldType} from "../../core/field/FieldType";
import {SharedUtils} from "../../core/utils/SharedUtils";

export const PriceListFields = () => {
    const fields: Field[] = [];

    fields.push(new FieldBuilder()
        .isIdentity(true)
        .visible(false)
        .type(FieldType.NONE)
        .name('id')
        .label('Id')
        .valueType('number')
        .required(true)
        .valueType('number')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('name')
        .label('Nombre')
        .width(100)
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.SWITCH)
        .name('active')
        .label('Activo')
        .defaultValue(true)
        .build())
    return fields;
}
