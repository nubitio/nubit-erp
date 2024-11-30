import {Field} from "../../core/field/Field";
import {FieldBuilder} from "../../core/field/FieldBuilder";
import {FieldType} from "../../core/field/FieldType";
import {Constants} from "../../core/common/Constants";
import {SharedUtils} from "../../core/utils/SharedUtils";
import {ColumnCellTemplateData} from "devextreme/ui/data_grid";
import {productService} from "../../service/product.service";
import {ProductDto} from "../../dto/product.dto";
import {DxFormEvents} from "../../core/form/DxForm";

export const RecipeFields = (formRef: DxFormEvents | null) => {
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
        .onSelect((e: any) => formRef?.setFieldValue('unit_name', e.itemData.unidad?.name ?? ''))
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
        .formatter((cell: ColumnCellTemplateData) => cell.value === 1 ? 'Receta' : 'Kit')
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
        .align('right')
        .col(6)
        .precision(2)
        .defaultValue(1)
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('unit_name')
        .label('Unidad')
        .formatter((cell: ColumnCellTemplateData) => cell.data.product.unidad?.name ?? '')
        .col(6)
        .filterable(false)
        .sortable(false)
        .readonly(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.SWITCH)
        .name('active')
        .label('Activo')
        .formatter(SharedUtils.booleanFormatter)
        .defaultValue(true)
        .build())

    return fields;
}

export const RecipeDetailFields = () => {
    const fields: Field[] = [];

    fields.push(new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('material')
        .label('Material')
        .width(100)
        .url(Constants.API_BASE_URL + 'products')
        .textField('descripcion')
        .valueField('producto_id')
        .formatter((cell: ColumnCellTemplateData) => cell.value?.descripcion)
        .onChange(async function (this: any, row: any, value: any, currentRow: any) {
            let product = await productService.getOne(value, true) as ProductDto;
            row.material = product;
            row.unidad = product.unidad?.name ?? '';
            this.defaultSetCellValue(row, product, currentRow);
        })
        .filterable(false)
        .sortable(false)
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NUMBER)
        .name('quantity')
        .label('Cantidad')
        .align('right')
        .valueType('number')
        .precision(2)
        .formatter((cell: ColumnCellTemplateData) => `${cell.value}  ${cell.data.material?.unidad?.name ?? ''}`)
        .filterable(false)
        .sortable(false)
        .required(true)
        .build());

    return fields;
}
