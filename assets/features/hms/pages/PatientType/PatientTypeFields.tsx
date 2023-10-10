import {Field} from "../../../../core/field/Field";
import {FieldBuilder} from "../../../../core/field/FieldBuilder";
import {FieldType} from "../../../../core/field/FieldType";

export const  PatientTypeFields = () => {
    const fields: Field[] = [];

    fields.push(new FieldBuilder()
        .isIdentity(true)
        .visible(false)
        .type(FieldType.NONE)
        .name('id')
        .required(true)
        .valueType('number')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('name')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('description')
        .build());
    return fields;
}
