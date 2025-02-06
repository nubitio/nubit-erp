import {Field} from "../../core/field/Field";
import {FieldBuilder} from "../../core/field/FieldBuilder";
import {FieldType} from "../../core/field/FieldType";
import {SharedUtils} from "../../core/utils/SharedUtils";
import {Constants} from "../../core/common/Constants";
import {ColumnCellTemplateData} from "devextreme/ui/data_grid";
import {textField} from "../../core/field/TextFieldBuilder";

export const CompanyFields = () => {
    const fields: Field[] = [];

    fields.push(new FieldBuilder()
        .isIdentity(true)
        .visible(false)
        .type(FieldType.NONE)
        .name('id')
        .label('Id')
        .required(true)
        .build());
    fields.push(textField()
        .name('ruc')
        .label('Ruc')
        .maxLength(11)
        .validators([
            {
                type: 'stringLength',
                options: {min: 11, max: 11, message: 'El RUC debe tener 11 dígitos'}
            },
            {
                type: 'pattern',
                options: {pattern: '^[0-9]+$', message: 'El RUC solo debe contener números'}
            },
        ])
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('businessName')
        .label('Razon social')
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('tradeName')
        .label('Nombre comercial')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('fiscalAddress')
        .label('Domicilio fiscal')
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('phone')
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
            return SharedUtils.imageFormatter(e.data?.logo?.path, e.data?.nombre_comercial);
        })
        .build());

    return fields;
}
