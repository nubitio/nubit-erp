import {Field} from "../../core/field/Field";
import {FieldBuilder} from "../../core/field/FieldBuilder";
import {FieldType} from "../../core/field/FieldType";
import {Constants} from "../../core/common/Constants";

export const ConsultationFields = () => {
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
        .label('Paciente')
        .url(Constants.API_BASE_URL + 'patients')
        .mode('remote')
        .valueField('id')
        .textField('razon_social')
        .formatter((value: any) => value?.full_name)
        .required(true)
        .valueType('number')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('physician')
        .label('Médico')
        .url(Constants.API_BASE_URL + 'physicians')
        .valueField('id')
        .textField('full_name')
        .formatter((value: any) => value?.full_name+'<br>'+value?.specialty?.name)
        .required(true)
        .valueType('number')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.DATE)
        .name('date')
        .label('Fecha')
        /*.formatter(SharedUtils.dateFormatter)*/
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('reason')
        .label('Motivo')
        .width(100)
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXTAREA)
        .name('diagnosis')
        .label('Diagnóstico')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXTAREA)
        .name('treatment')
        .label('Tratamiento')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXTAREA)
        .name('notes')
        .label('Notas')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NONE)
        .readonly(true)
        .name('queue')
        .label('Turno')
        .build())

    return fields
}
