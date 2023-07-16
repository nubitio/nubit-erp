import {Field} from "../../core/field/Field";
import {FieldBuilder} from "../../core/field/FieldBuilder";
import {FieldType} from "../../core/field/FieldType";
import {Constants} from "../../core/common/Constants";
import {ColumnCellTemplateData} from "devextreme/ui/data_grid";

export const KardexFields = () => {
    const fields: Field[] = [];

    fields.push(new FieldBuilder()
        .isIdentity(true)
        .visible(false)
        .type(FieldType.NONE)
        .name('id')
        .label('Id')
        .required(true)
        .valueType('number')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('codigo')
        .label('Codigo')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('producto')
        .label('Producto')
        .url(Constants.API_BASE_URL + '/products')
        .valueField('producto_id')
        .textField('descripcion')
        .formatter((cell: ColumnCellTemplateData) => cell.value.descripcion)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NUMBER)
        .name('saldo')
        .label('Saldo')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NUMBER)
        .name('entradas')
        .label('Entradas')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NUMBER)
        .name('salidas')
        .label('Salidas')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NUMBER)
        .name('stock')
        .label('Stock')
        .build());

    return fields;
}
