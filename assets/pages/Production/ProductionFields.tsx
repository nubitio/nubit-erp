import {Field} from "../../core/field/Field";
import {FieldBuilder} from "../../core/field/FieldBuilder";
import {FieldType} from "../../core/field/FieldType";
import {Constants} from "../../core/common/Constants";
import {SharedUtils} from "../../core/utils/SharedUtils";
import {ColumnCellTemplateData} from "devextreme/ui/data_grid";
import {productService} from "../../services/product.service";
import {ProductDto} from "../../dto/product.dto";
import {httpClient} from "../../core/http/HttpClient";
import Recipe from "../Recipe";
import {DxFormEvents} from "../../core/form/DxForm";

export const ProductionFields = (form: DxFormEvents|null) => {
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
        .formatter((cell: ColumnCellTemplateData) => cell.value?.descripcion)
        /*.onSelect((record: any, field: any) => {
            let filter = JSON.stringify({
                'field': 'product',
                'op': 'equal',
                'value': record.producto_id
            })

            let recipeUrl = Constants.API_BASE_URL + 'recipes?filterRules=[' + filter + ']';

            let recipeField = form.getField('recipe');
            recipeField.combobox('reload', recipeUrl);
            recipeField.combobox('clear');
        })*/
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.DATE)
        .name('scheduled_date')
        .label('Fecha programada')
        .formatter((cell: ColumnCellTemplateData) => SharedUtils.dateFormatter(cell.value))
        .defaultValue(new Date())
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NUMBER)
        .name('quantity')
        .label('Cantidad')
        .align('center')
        .precision(2)
        .defaultValue(1)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('recipe')
        .label('Receta')
        .url(Constants.API_BASE_URL + 'recipes')
        .valueField('id')
        .textField('name')
        .formatter((value: any) => value?.name)
        .onSelect((e: any) => {
            //dialog.setLoading(true, 'Cargando receta...');
            httpClient.get<any>(Recipe.API_URL + '/' + e.itemData.id + '/details').subscribe((response) => {
                form?.setDetailData(response.data);
            }).add(() => {
                //dialog.setLoading(false)
            });
        })
        .valueType('number')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('employee')
        .label('Responsable')
        .url(Constants.API_BASE_URL + 'employees')
        .valueField('id')
        .textField('full_name')
        .formatter((cell: ColumnCellTemplateData) => cell.value?.full_name)
        .required(true)
        .valueType('number')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('production_line')
        .label('Linea de producción')
        .url(Constants.API_BASE_URL + 'combo-options')
        .filters([{
            'field': '_key',
            'operator': '=',
            'value': 'linea'
        }])
        .valueType('number')
        .textField('_value')
        .valueField('id')
        .formatter((cell: ColumnCellTemplateData) => cell.value?._value)
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENUM)
        .name('status')
        .label('Estado')
        .data([
            {value: 1, text: "Pendiente"},
            {value: 2, text: "En proceso"},
            {value: 3, text: "Finalizado"},
        ])
        .formatter((cell: ColumnCellTemplateData) => {
            switch (cell.value) {
                case 1:
                    return 'Pendiente';
                case 2:
                    return 'En proceso';
                case 3:
                    return 'Finalizado';
            }
        })
        .defaultValue(1)
        .build())

    return fields;
}

export const ProductionDetailFields = () => {
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
        .align('center')
        .valueType('number')
        .precision(2)
        .filterable(false)
        .sortable(false)
        .required(true)
        .build())

    return fields;
}
