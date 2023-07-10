import {Field} from "../../core/field/Field";
import {FieldBuilder} from "../../core/field/FieldBuilder";
import {FieldType} from "../../core/field/FieldType";
import {Constants} from "../../core/common/Constants";
import {SharedUtils} from "../../core/utils/SharedUtils";
import {ColumnCellTemplateData} from "devextreme/ui/data_grid";

export const BranchFields = () =>{
    const fields:Field[] = [];

    fields.push(new FieldBuilder()
        .isIdentity(true)
        .visible(false)
        .type(FieldType.NONE)
        .name('local_id')
        .label('Local id')
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('empresa')
        .label('Empresa')
        .url(Constants.API_BASE_URL + 'companies')
        .valueField('empresa_id')
        .textField('razon_social')
        .required(true)
        .formatter((cell: ColumnCellTemplateData) => cell.data.empresa.razon_social)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('descripcion')
        .label('Descripcion')
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('direccion')
        .label('Direccion')
        .width(100)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('telefono')
        .label('Telefono')
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
