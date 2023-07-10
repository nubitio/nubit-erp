import {Field} from "../../core/field/Field";
import {FieldBuilder} from "../../core/field/FieldBuilder";
import {FieldType} from "../../core/field/FieldType";
import {Constants} from "../../core/common/Constants";
import {SharedUtils} from "../../core/utils/SharedUtils";
import {ColumnCellTemplateData} from "devextreme/ui/data_grid";

export const UserFields = () => {
    const fields: Field[] = [];

    fields.push(new FieldBuilder()
        .isIdentity(true)
        .visible(false)
        .type(FieldType.NONE)
        .name('usuario_id')
        .label('Usuario id')
        .required(true)
        .valueType('number')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('username')
        .label('Username')
        .width(100)
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.PASSWORD)
        .name('password')
        .label('Password')
        .visible(false)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('pin')
        .label('Pin')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('rol')
        .label('Rol')
        .url(Constants.API_BASE_URL + 'roles')
        .textField('descripcion')
        .valueField('rol_id')
        .valueType('number')
        .required(true)
        .formatter((cell: ColumnCellTemplateData) => cell.data.rol?.descripcion)
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
