import {Field} from "../../core/field/Field";
import {FieldBuilder} from "../../core/field/FieldBuilder";
import {FieldType} from "../../core/field/FieldType";

export const MilitaryPatientFields = () => {
    const fields: Field[] = [];

    fields.push(new FieldBuilder()
        .isIdentity(true)
        .visible(false)
        .type(FieldType.NONE)
        .name('id')
        .label('Id')
        .required(true)
        .valueType('number')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('patient')
        .label('Patient')
        .textField('id')
        .valueField('id')
        .valueType('number')
        .visible(false)
        .hidden(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('cip_code')
        .label('Código CIP')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('rank')
        .label('Grado')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('weapon_code')
        .label('Arma')
        .build())

    return fields;
}
