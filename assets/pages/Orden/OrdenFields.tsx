import {Field} from "../../core/field/Field";
import {FieldBuilder} from "../../core/field/FieldBuilder";
import {FieldType} from "../../core/field/FieldType";
import {ColumnCellTemplateData} from "devextreme/ui/data_grid";
import {SharedUtils} from "../../core/utils/SharedUtils";
import {Constants} from "../../core/common/Constants";
import {productService} from "../../services/product.service";
import {ProductDto} from "../../dto/product.dto";
import NumberUtils from "../../core/utils/NumberUtils";

export const OrdenFields = () => {
    const fields: Field[] = [];

    fields.push(new FieldBuilder()
        .isIdentity(true)
        .visible(false)
        .type(FieldType.NONE)
        .name('orden_id')
        .label('Orden id')
        .required(true)
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
        .name('mesa')
        .label('mesa')
        .url(Constants.API_BASE_URL + 'mesas')
        .valueField('mesa_id')
        .textField('descripcion')
        .required(true)
        .formatter((cell: ColumnCellTemplateData) => cell.data.mesa.descripcion)
        .build());
    fields.push(new FieldBuilder()
        .visible(false)
        .type(FieldType.NONE)
        .name('mesa_origen')
        .label('Mesa origen')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('nota')
        .label('Nota')
        .build());
    fields.push(new FieldBuilder()
        .visible(false)
        .type(FieldType.NONE)
        .name('estado_cocina')
        .label('Estado cocina')
        .build());
    fields.push(new FieldBuilder()
        .visible(false)
        .type(FieldType.NONE)
        .name('estado_barra')
        .label('Estado barra')
        .build());
    fields.push(new FieldBuilder()
        .visible(false)
        .type(FieldType.NONE)
        .name('usuario_id')
        .label('Usuario id')
        .build());
    fields.push(new FieldBuilder()
        .visible(false)
        .type(FieldType.NONE)
        .name('glosa')
        .label('Glosa')
        .build());
    fields.push(new FieldBuilder()
        .visible(false)
        .type(FieldType.NONE)
        .name('estado')
        .label('Estado')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.SWITCH)
        .name('anulado')
        .label('Anulado')
        .defaultValue(true)
        .formatter(SharedUtils.dxBooleanFormatter)
        .align('center')
        .build());
    fields.push(new FieldBuilder()
        .visible(false)
        .type(FieldType.NONE)
        .name('user_create')
        .label('User create')
        .build());
    fields.push(new FieldBuilder()
        .visible(false)
        .type(FieldType.NONE)
        .name('user_update')
        .label('User update')
        .build());

    return fields;
}

export const OrdenDetailFields = () => {
    const fields: Field[] = [];

    fields.push(new FieldBuilder()
        .isIdentity(true)
        .visible(false)
        .type(FieldType.NONE)
        .name('id')
        .label('Id')
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .visible(false)
        .type(FieldType.NONE)
        .name('orden_id')
        .label('Orden id')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('producto')
        .label('Producto')
        .minWidth(200)
        .url(Constants.API_BASE_URL + 'products')
        .mode('remote')
        .valueField('producto_id')
        .textField('descripcion')
        .onChange(async function (this: any, row: any, value: any, currentRow: any) {
            let product = await productService.getOne(value, true) as ProductDto;
            let price = product.precio;
            row.cantidad = 1;
            row.producto = product;
            row.precio_unitario = price;
            row.precio_total = price;
            this.defaultSetCellValue(row, product, currentRow);
        })
        .formatter((cell: ColumnCellTemplateData) => cell.value?.descripcion)
        .required(true)
        .filterable(false)
        .sortable(false)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NUMBER)
        .name('cantidad')
        .label('Cantidad')
        .align('center')
        .precision(2)
        .defaultValue(1)
        .onChange(async function (this: any, row: any, value: any, currentRow: any) {
            if (value === '') return;
            row.precio_total = currentRow.precio_unitario * value;
            this.defaultSetCellValue(row, value, currentRow);
        })
        .required(true)
        .filterable(false)
        .sortable(false)
        .build());
    fields.push(new FieldBuilder()
        .visible(false)
        .type(FieldType.NONE)
        .name('cantidad_helper')
        .label('Cantidad helper')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.CURRENCY)
        .name('precio_unitario')
        .label('Precio')
        .align('right')
        .onChange(async function (this: any, row: any, value: any, currentRow: any) {
            if (value === '') return;
            row.precio_total = currentRow.cantidad * value;
            this.defaultSetCellValue(row, value, currentRow);
        })
        .formatter((cell: ColumnCellTemplateData) => NumberUtils.formatCurrency(cell.value))
        .required(true)
        .filterable(false)
        .sortable(false)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.CURRENCY)
        .name('precio_total')
        .label('Total')
        .align('right')
        .readonly(true)
        .formatter((cell: ColumnCellTemplateData) => NumberUtils.formatCurrency(cell.value))
        .required(true)
        .filterable(false)
        .sortable(false)
        .build());
    fields.push(new FieldBuilder()
        .visible(false)
        .type(FieldType.NONE)
        .name('nota')
        .label('Nota')
        .build());
    fields.push(new FieldBuilder()
        .visible(false)
        .type(FieldType.NONE)
        .name('devolver')
        .label('Devolver')
        .build());
    fields.push(new FieldBuilder()
        .visible(false)
        .type(FieldType.NONE)
        .name('desechar')
        .label('Desechar')
        .build());
    fields.push(new FieldBuilder()
        .visible(false)
        .type(FieldType.NONE)
        .name('billed')
        .label('Billed')
        .build());
    fields.push(new FieldBuilder()
        .visible(false)
        .type(FieldType.NONE)
        .name('billed_quantity')
        .label('Billed quantity')
        .build());
    fields.push(new FieldBuilder()
        .visible(false)
        .type(FieldType.NONE)
        .name('estado_cocina')
        .label('Estado cocina')
        .build());
    fields.push(new FieldBuilder()
        .visible(false)
        .type(FieldType.NONE)
        .name('estado_barra')
        .label('Estado barra')
        .build());
    fields.push(new FieldBuilder()
        .visible(false)
        .type(FieldType.NONE)
        .name('estado')
        .label('Estado')
        .build());

    return fields;
}
