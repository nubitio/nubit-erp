import {Field} from "../../core/field/Field";
import {FieldBuilder} from "../../core/field/FieldBuilder";
import {FieldType} from "../../core/field/FieldType";
import {SharedUtils} from "../../core/utils/SharedUtils";
import {Constants} from "../../core/common/Constants";
import {ColumnCellTemplateData} from "devextreme/ui/data_grid";

export const CompanyFields = () => {
    const fields: Field[] = [];

    fields.push(new FieldBuilder()
        .isIdentity(true)
        .visible(false)
        .type(FieldType.NONE)
        .name('empresa_id')
        .label('Empresa id')
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('ruc')
        .label('Ruc')
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('razon_social')
        .label('Razon social')
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('nombre_comercial')
        .label('Nombre comercial')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('domicilio_fiscal')
        .label('Domicilio fiscal')
        .width(100)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('telefono')
        .label('Telefono')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.FILE)
        .name('logo')
        .label('Logo')
        .align('center')
        .url(Constants.API_BASE_URL + 'media')
        .accept('image/*')
        .formatter((e: ColumnCellTemplateData) => {
            return SharedUtils.imageFormatter(e.data.logo.path);
        })
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.SWITCH)
        .name('estado')
        .label('Activo')
        .align('center')
        .formatter(SharedUtils.booleanFormatter)
        .defaultValue(true)
        .build());

    return fields;
}
