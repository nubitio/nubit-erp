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
        .name('product_id')
        .label('Id')
        .required(true)
        .valueType('number')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('product_code')
        .label('Codigo')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('product_name')
        .label('Producto')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NUMBER)
        .name('saldo')
        .label('Saldo')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NUMBER)
        .name('income')
        .label('Entradas')
        .align('center')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NUMBER)
        .name('outcome')
        .label('Salidas')
        .align('center')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NUMBER)
        .name('stock')
        .label('Stock')
        .align('center')
        .build());

    return fields;
}
