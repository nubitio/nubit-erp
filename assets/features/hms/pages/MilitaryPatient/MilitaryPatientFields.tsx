import {Field} from "../../../../core/field/Field";
import {FieldBuilder} from "../../../../core/field/FieldBuilder";
import {FieldType} from "../../../../core/field/FieldType";
import {PatientFields} from "../Patient/PatientFields"
import {CustomerFields} from "../../../../pages/Customer/CustomerFields";
import {DxFormEvents} from "../../../../core/form/DxForm";

export const MilitaryPatientFields = (formRef: DxFormEvents|null) => {
    const fields: Field[] = [...CustomerFields(formRef),  ...PatientFields()];

    fields.forEach((field: Field) => {
        if (field.isIdentity) {  field.isIdentity = false;       }
        if (field.name == 'id') {  field.name = 'id_2';       }
    });

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
