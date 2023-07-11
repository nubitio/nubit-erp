import {Field} from "../../core/field/Field";
import {FieldBuilder} from "../../core/field/FieldBuilder";
import {FieldType} from "../../core/field/FieldType";
import {SharedUtils} from "../../core/utils/SharedUtils";

export const SupplierFields = () => {
    const fields: Field[] = [];

    fields.push(new FieldBuilder()
        .isIdentity(true)
        .visible(false)
        .type(FieldType.NONE)
        .name('proveedor_id')
        .label('Proveedor id')
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('ruc')
        .label('R.U.C.')
        .validators(['length[11, 11]'])
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXTAREA)
        .name('razon_social')
        .label('Razón Social')
        .width('100%')
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('domicilio_fiscal')
        .label('Dirección')
        .width('100%')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('telefono')
        .label('Teléfono')
        .align('center')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.SWITCH)
        .name('estado')
        .label('Activo')
        .formatter(SharedUtils.dxBooleanFormatter)
        .align('center')
        .defaultValue(true)
        .build());

    return fields;
}
