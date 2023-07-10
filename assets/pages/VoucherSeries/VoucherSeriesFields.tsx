import {Field} from "../../core/field/Field";
import {FieldBuilder} from "../../core/field/FieldBuilder";
import {FieldType} from "../../core/field/FieldType";
import {Constants} from "../../core/common/Constants";
import {SharedUtils} from "../../core/utils/SharedUtils";
import {ColumnCellTemplateData} from "devextreme/ui/data_grid";

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
        .name('local')
        .label('Local')
        .url(Constants.API_BASE_URL + 'branches')
        .valueField('local_id')
        .textField('descripcion')
        .formatter((cell: ColumnCellTemplateData) => cell.data.local.descripcion)
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('tipo_comprobante')
        .label('Tipo comprobante')
        .url(Constants.API_BASE_URL + 'voucher-types')
        .valueField('codigo')
        .textField('descripcion')
        .formatter((cell: ColumnCellTemplateData) => cell.data.tipo_comprobante.descripcion)
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
