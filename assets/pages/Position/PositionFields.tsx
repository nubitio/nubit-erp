import {Field} from "../../core/field/Field";
import {FieldBuilder} from "../../core/field/FieldBuilder";
import {FieldType} from "../../core/field/FieldType";
import {Constants} from "../../core/common/Constants";
import {ColumnCellTemplateData} from "devextreme/ui/data_grid";

export const PositionFields = () => {
    const fields: Field[] = [];

    fields.push(new FieldBuilder()
        .isIdentity(true)
        .visible(false)
        .type(FieldType.NONE)
        .name('id')
        .label('Id')
        .valueType('number')
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('department')
        .label('Department')
        .url(Constants.API_BASE_URL + 'departments')
        .valueField('id')
        .textField('name')
        .formatter((cell: ColumnCellTemplateData) => cell.data.department.name)
        .required(true)
        .valueType('number')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('name')
        .label('Name')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('description')
        .label('Description')
        .build())

    return fields;
}
