import {Field} from "../../../../core/field/Field";
import {FieldBuilder} from "../../../../core/field/FieldBuilder";
import {FieldType} from "../../../../core/field/FieldType";
import {Constants} from "../../../../core/common/Constants";
import {ColumnCellTemplateData} from "devextreme/ui/data_grid";

export const PatientFields = () => {
    const fields: Field[] = [];

    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('medical_record_number')
        .label('Nro. Historia Clínica')
        .build());
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
        .type(FieldType.NONE)
        .name('customer_id')
        .label('Customer id')
        .required(true)
        .visible(false)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('type')
        .label('Tipo')
        .url(Constants.API_BASE_URL + 'patient-types')
        .valueField('id')
        .textField('name')
        .formatter((cell: ColumnCellTemplateData) => cell.data?.type?.name)
        .required(true)
        .valueType('number')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('insurance_provider')
        .label('Aseguradora')
        .build());

    return fields;
}
