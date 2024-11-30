import {Field} from "../../core/field/Field";
import {FieldBuilder} from "../../core/field/FieldBuilder";
import {FieldType} from "../../core/field/FieldType";
import {ColumnCellTemplateData} from "devextreme/ui/data_grid";
import {SharedUtils} from "../../core/utils/SharedUtils";
import {Constants} from "../../core/common/Constants";
import NumberUtils from "../../core/utils/NumberUtils";
import {userInformation} from "../../core/common/UserInformation";
import {productService} from "../../service/product.service";
import {ProductDto} from "../../dto/product.dto";

export const InboundFields = () => {
    const fields: Field[] = [];

    fields.push(new FieldBuilder()
        .isIdentity(true)
        .visible(false)
        .type(FieldType.NONE)
        .name('movimiento_id')
        .label('Movimiento id')
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NUMBER)
        .name('tipo_id')
        .label('Tipo id')
        .defaultValue(1)
        .hidden(true)
        .visible(false)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.DATE)
        .name('fecha')
        .label('Fecha')
        .defaultValue(new Date())
        .formatter((cell: ColumnCellTemplateData) => SharedUtils.dateFormatter(cell.value))
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('operacion')
        .label('Operacion')
        .url(Constants.API_BASE_URL + 'operations')
        .filters([{
            field: 'tipo_id',
            operator: '=',
            value: 1
        }])
        .valueField('operacion_id')
        .textField('descripcion')
        .formatter((cell: ColumnCellTemplateData) => cell.value?.descripcion)
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('almacen')
        .label('Almacen')
        .url(Constants.API_BASE_URL + 'branches/' + userInformation().currentBranchId + '/warehouses')
        .byKeyUrl(Constants.API_BASE_URL + 'warehouses')
        .valueField('almacen_id')
        .textField('descripcion')
        //.defaultValue(userInformation().currentWarehouseId)
        .formatter((cell: ColumnCellTemplateData) => cell.value?.descripcion)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('proveedor')
        .label('Proveedor')
        .url(Constants.API_BASE_URL + 'suppliers')
        .valueField('proveedor_id')
        .textField('razon_social')
        .formatter((cell: ColumnCellTemplateData) => cell.value?.razon_social)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXTAREA)
        .name('glosa')
        .label('Glosa')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.CURRENCY)
        .name('total')
        .label('Total')
        .align('right')
        .formatter((cell: ColumnCellTemplateData) => NumberUtils.formatCurrency(cell.value))
        .readonly(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NONE)
        .name('estado')
        .label('Estado')
        .build());

    return fields;
}

export const InboundDetailFields = () => {
    const fields: Field[] = [];

    fields.push(new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('producto')
        .label('Producto')
        .minWidth(200)
        .url(Constants.API_BASE_URL + 'products')
        .valueField('producto_id')
        .textField('descripcion')
        .formatter((cell: ColumnCellTemplateData) => cell.value?.descripcion)
        .onChange(async function (this: any, row: any, value: any, currentRow: any) {
            let product = await productService.getOne(value, true) as ProductDto;
            row.producto = product;
            this.defaultSetCellValue(row, product, currentRow);
        })
        .sortable(false)
        .filterable(false)
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NUMBER)
        .name('cantidad')
        .label('Cantidad')
        .align('center')
        .onChange(function (this: any, row: any, value: any, currentRow: any) {
            if (value === '') return;
            row.precio_total = currentRow.precio_unitario * value;
            this.defaultSetCellValue(row, value, currentRow);
        })
        .required(true)
        .sortable(false)
        .filterable(false)
        .valueType('number')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.CURRENCY)
        .name('precio_unitario')
        .label('Precio unitario')
        .formatter((cell: ColumnCellTemplateData) => NumberUtils.formatCurrency(cell.value))
        .onChange(function (this: any, row: any, value: any, currentRow: any) {
            if (value === '') return;
            row.precio_total = currentRow.cantidad * value;
            this.defaultSetCellValue(row, value, currentRow);
        })
        .align('right')
        .valueType('number')
        .sortable(false)
        .filterable(false)
        .valueType('number')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.CURRENCY)
        .name('precio_total')
        .label('Precio total')
        .align('right')
        .formatter((cell: ColumnCellTemplateData) => NumberUtils.formatCurrency(cell.value))
        .sortable(false)
        .filterable(false)
        .valueType('number')
        .build());

    return fields;
}
