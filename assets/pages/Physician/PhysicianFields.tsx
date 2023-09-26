import {Field} from "../../core/field/Field";
import {FieldBuilder} from "../../core/field/FieldBuilder";
import {FieldType} from "../../core/field/FieldType";
import {Constants} from "../../core/common/Constants";
import {ColumnCellTemplateData} from "devextreme/ui/data_grid";

export const PhysicianFields = () => {
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
        .name('employee')
        .label('Empleado')
        .url(Constants.API_BASE_URL + 'employees')
        .valueField('id')
        .textField('full_name')
        .formatter((value: any) => value?.first_name + ' ' + value?.last_name)
        .valueType('number')
        .formatter((cell: ColumnCellTemplateData) => cell.data.full_name)
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('specialty')
        .label('Especialidad')
        .url(Constants.API_BASE_URL + 'specialties')
        .valueField('id')
        .textField('name')
        .formatter((cell: ColumnCellTemplateData) => cell.data.specialty.name)
        .valueType('number')
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('licence')
        .label('Licencia')
        .build())

    return fields;
}
