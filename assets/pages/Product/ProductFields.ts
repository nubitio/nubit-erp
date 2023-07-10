import {Field} from "../../core/field/Field";
import {FieldBuilder} from "../../core/field/FieldBuilder";
import {FieldType} from "../../core/field/FieldType";
import {Constants} from "../../core/common/Constants";
import NumberUtils from "../../core/utils/NumberUtils";
import {SharedUtils} from "../../core/utils/SharedUtils";
import {ColumnCellTemplateData} from "devextreme/ui/data_grid";

export const ProductFields = () => {
    const fields: Field[] = [];

    fields.push(new FieldBuilder()
        .isIdentity(true)
        .visible(false)
        .type(FieldType.NONE)
        .name('producto_id')
        .label('Producto id')
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('codigo')
        .label('Código')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENUM)
        .name('tipo_id')
        .label('Tipo')
        .data([
            {value: 1, text: 'Fisico'},
            {value: 2, text: 'Servicio'},
        ])
        .required(true)
        .formatter((cell: ColumnCellTemplateData) => {
            return cell.value === 1 ? 'Fisico' : 'Servicio';
        })
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('descripcion')
        .label('Descripción')
        .width(100)
        .sortable(true)
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('categoria')
        .label('Categoría')
        .url(Constants.API_BASE_URL + 'categories')
        .valueField('categoria_id')
        .textField('descripcion')
        .required(true)
        .formatter((cell: ColumnCellTemplateData) => cell.value?.descripcion)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('unidad')
        .label('Unidad')
        .url(Constants.API_BASE_URL + 'unit-of-measures')
        .valueField('unidad_id')
        .textField('name')
        .required(true)
        .formatter((cell: ColumnCellTemplateData) => cell.data.tipo_id)
        .valueType('number')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.CURRENCY)
        .name('precio')
        .label('Precio')
        .align('right')
        .formatter((cell: ColumnCellTemplateData) => NumberUtils.formatCurrency(cell.value))
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.FILE)
        .name('imagen')
        .label('Imagen')
        .align('center')
        .formatter(SharedUtils.dxImageFormatter)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.SWITCH)
        .name('estado')
        .label('Activo')
        .align('center')
        .formatter(SharedUtils.dxStatusFormatter)
        .defaultValue(true)
        .build());

    return fields;
}
