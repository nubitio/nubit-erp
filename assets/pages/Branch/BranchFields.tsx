import {Field} from "../../core/field/Field";
import {FieldBuilder} from "../../core/field/FieldBuilder";
import {FieldType} from "../../core/field/FieldType";
import {Constants} from "../../core/common/Constants";
import {ColumnCellTemplateData} from "devextreme/ui/data_grid";

export const BranchFields = () => {
    const fields: Field[] = [];

    fields.push(new FieldBuilder()
        .isIdentity(true)
        .visible(false)
        .type(FieldType.NONE)
        .name('id')
        .label('Local id')
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('company')
        .label('Empresa')
        .url(Constants.API_BASE_URL + 'companies')
        .valueField('id')
        .textField('businessName')
        .required(true)
        .formatter((cell: ColumnCellTemplateData) => cell.data.company?.businessName)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('name')
        .label('Descripcion')
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('address')
        .label('Direccion')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('phone')
        .label('Telefono')
        .build());

    return fields;
}
