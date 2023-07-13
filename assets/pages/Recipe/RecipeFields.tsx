import {Field} from "../../core/field/Field";
import {FieldBuilder} from "../../core/field/FieldBuilder";
import {FieldType} from "../../core/field/FieldType";
import {Constants} from "../../core/common/Constants";
import {SharedUtils} from "../../core/utils/SharedUtils";
import {ColumnCellTemplateData} from "devextreme/ui/data_grid";

export const RecipeFields = () => {
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
        .name('product')
        .label('Producto')
        .width(100)
        .url(Constants.API_BASE_URL + 'products')
        .mode('remote')
        .valueField('producto_id')
        .textField('descripcion')
        .formatter((cell: ColumnCellTemplateData) => cell.data.name)
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENUM)
        .name('type_id')
        .label('Tipo')
        .data([
            {value: 1, text: 'Receta'},
            {value: 2, text: 'Kit'},
        ])
        .formatter((value) => {
            return value === 1 ? 'Receta' : 'Kit';
        })
        .defaultValue(1)
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('reference')
        .label('Referencia')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NUMBER)
        .name('quantity')
        .label('Cantidad')
        .precision(2)
        .defaultValue(1)
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.SWITCH)
        .name('active')
        .label('Activo')
        .formatter(SharedUtils.dxActiveFormatter)
        .defaultValue(true)
        .build())

    return fields;
}
