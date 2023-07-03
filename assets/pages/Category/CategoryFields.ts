import {Field} from "../../core/field/Field";
import {FieldBuilder} from "../../core/field/FieldBuilder";
import {FieldType} from "../../core/field/FieldType";
import {Constants} from "../../core/common/Constants";
import {SharedUtils} from "../../core/utils/SharedUtils";
import {ColumnCellTemplateData} from "devextreme/ui/data_grid";

export const categoryFields = () => {
    const fields: Field[] = [];

    fields.push(new FieldBuilder()
        .isIdentity(true)
        .visible(false)
        .type(FieldType.NONE)
        .name('categoria_id')
        .label('Categoria id')
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('descripcion')
        .label('Descripcion')
        .width(100)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('parent')
        .label('Categoria padre')
        .url(Constants.API_BASE_URL + 'categories')
        .valueField('categoria_id')
        .textField('descripcion')
        .formatter((value: any, row: any) => {
            return row.parent?.descripcion;
        })
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.FILE)
        .name('imagen')
        .label('Imagen')
        .formatter((val: any, row: any) => {
            return SharedUtils.imageFormatter(val, row['descripcion'], 'uploads/images/')
        })
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.SWITCH)
        .name('estado')
        .label('Activo')
        .formatter(SharedUtils.booleanFormatter)
        .defaultValue(true)
        .build());

    return fields;
}

export const dxCategoryFields = () => {
    const fields: Field[] = [];

    fields.push(new FieldBuilder()
        .isIdentity(true)
        .visible(false)
        .type(FieldType.NONE)
        .name('categoria_id')
        .label('Categoria id')
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('descripcion')
        .label('Descripcion')
        .width(100)
        .sortable(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('parent')
        .label('Categoria padre')
        .url(Constants.API_BASE_URL + 'categories')
        .valueField('categoria_id')
        .textField('descripcion')
        .formatter((cell: ColumnCellTemplateData) => cell.data.parent?.descripcion)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.FILE)
        .name('imagen')
        .label('Imagen')
        .url(Constants.API_BASE_URL + 'media')
        .accept('image/*')
        .formatter(SharedUtils.dxImageFormatter)
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
