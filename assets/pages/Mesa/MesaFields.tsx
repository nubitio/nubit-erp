import {Field} from "../../core/field/Field";
import {FieldBuilder} from "../../core/field/FieldBuilder";
import {FieldType} from "../../core/field/FieldType";
import {Constants} from "../../core/common/Constants";
import {SharedUtils} from "../../core/utils/SharedUtils";
import {ColumnCellTemplateData} from "devextreme/ui/data_grid";

export const MesaFields = () => {
    const fields: Field[] = [];

    fields.push(new FieldBuilder()
        .isIdentity(true)
        .visible(false)
        .type(FieldType.NONE)
        .name('mesa_id')
        .label('Mesa id')
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('ambiente')
        .label('Ambiente')
        .url(Constants.API_BASE_URL + 'ambientes')
        .valueField('ambiente_id')
        .textField('descripcion')
        .required(true)
        .formatter((cell: ColumnCellTemplateData) => cell.data.ambiente.descripcion)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('descripcion')
        .label('Descripcion')
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .visible(false)
        .type(FieldType.NONE)
        .name('position')
        .label('posiciòn')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NUMBER)
        .name('capacity')
        .label('capacidad')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.SWITCH)
        .name('estado')
        .label('Estado')
        .defaultValue(true)
        .formatter(SharedUtils.dxBooleanFormatter)
        .align('center')
        .build());

    return fields;
}
