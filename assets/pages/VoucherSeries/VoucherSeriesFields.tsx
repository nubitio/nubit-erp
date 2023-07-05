import {Field} from "../../core/field/Field";
import {FieldBuilder} from "../../core/field/FieldBuilder";
import {FieldType} from "../../core/field/FieldType";
import {Constants} from "../../core/common/Constants";
import {SharedUtils} from "../../core/utils/SharedUtils";

export const VoucherSeriesFields = () => {
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
        .type(FieldType.ENTITY)
        .name('local_id')
        .label('Local')
        .url(Constants.API_BASE_URL + 'branches')
        .valueField('local_id')
        .textField('descripcion')
        .visible(false)
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NONE)
        .name('local')
        .label('Local')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('tipo_comprobante_id')
        .label('Tipo comprobante')
        .url(Constants.API_BASE_URL + 'voucher-types')
        .valueField('codigo')
        .textField('descripcion')
        .visible(false)
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NONE)
        .name('tipo_comprobante')
        .label('Tipo comprobante')
        .width(100)
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('serie')
        .label('Serie')
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('serie_asociada')
        .label('Serie asociada')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.SWITCH)
        .name('active')
        .label('Activo')
        .formatter(SharedUtils.dxBooleanFormatter)
        .defaultValue(true)
        .build());

    return fields;
}
