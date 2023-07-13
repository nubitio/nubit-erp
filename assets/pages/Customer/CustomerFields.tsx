import {Field} from "../../core/field/Field";
import {FieldBuilder} from "../../core/field/FieldBuilder";
import {FieldType} from "../../core/field/FieldType";
import {Constants} from "../../core/common/Constants";
import {SharedUtils} from "../../core/utils/SharedUtils";
import {ColumnCellTemplateData} from "devextreme/ui/data_grid";

export const CustomerFields = () => {
    const fields: Field[] = [];

    fields.push(new FieldBuilder()
        .isIdentity(true)
        .name('ruc')
        .label('RUC/DNI')
        .validators(['validCustomerId'])
        .required(true)
        .sortable(true)
        .valueType('string')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXTAREA)
        .name('razon_social')
        .label('Cliente')
        .required(true)
        .sortable(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXTAREA)
        .name('domicilio_fiscal')
        .label('Domicilio')
        .width(100)
        .build());
    fields.push(new FieldBuilder()
        .name('telefono')
        .label('Teléfono')
        .build());
    fields.push(new FieldBuilder()
        .name('email')
        .label('Email')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('price_list')
        .label('Tarifa')
        .url(Constants.API_BASE_URL + 'price-lists')
        .valueField('id')
        .textField('name')
        .formatter((cell: ColumnCellTemplateData) => cell.data.price_list.name)
        .valueType('number')
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
