import {Field} from "../../core/field/Field";
import {FieldBuilder} from "../../core/field/FieldBuilder";
import {FieldType} from "../../core/field/FieldType";
import {Constants} from "../../core/common/Constants";
import {ColumnCellTemplateData} from "devextreme/ui/data_grid";

export const UserFields = () => {
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
        .type(FieldType.TEXT)
        .name('username')
        .label('Username')
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.PASSWORD)
        .name('plainPassword')
        .label('Contraseña')
        .helpText('Deje en blanco si no desea cambiar la contraseña')
        .visible(false)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('role')
        .label('Rol')
        .url(Constants.API_BASE_URL + 'roles')
        .valueField('id')
        .textField('name')
        .valueType('number')
        .required(true)
        .formatter((cell: ColumnCellTemplateData) => cell.value?.name)
        .build());

    return fields;
}
