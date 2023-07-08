import {Field} from "../../core/field/Field";
import {FieldBuilder} from "../../core/field/FieldBuilder";
import {FieldType} from "../../core/field/FieldType";
import {SharedUtils} from "../../core/utils/SharedUtils";
import {Constants} from "../../core/common/Constants";
import {ColumnCellTemplateData} from "devextreme/ui/data_grid";

export const EmployeeFields = () => {
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
        .name('id_number')
        .label('DNI')
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('first_name')
        .label('Nombres')
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('last_name')
        .label('Apellidos')
        .width(100)
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENUM)
        .name('gender')
        .label('Sexo')
        .required(true)
        .data([
            {value: 1, text: "Masculino"},
            {value: 2, text: "Femenino"}
        ])
        .formatter((value: number) => {
            if (value === 1) {
                return 'Masculino';
            } else if (value === 2) {
                return 'Femenino';
            } else {
                return '';
            }
        })
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.DATE)
        .name('birthdate')
        .label('Fecha de nacimiento')
        .formatter((cell: ColumnCellTemplateData) => SharedUtils.dateFormatter(cell.value))
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('address')
        .label('Dirección')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('phone')
        .label('Teléfono')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.DATE)
        .name('hire_date')
        .label('Fecha de contratación')
        .formatter((cell: ColumnCellTemplateData) => SharedUtils.dateFormatter(cell.value))
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('position')
        .label('Cargo')
        .url(Constants.API_BASE_URL + 'positions')
        .valueField('id')
        .textField('name')
        /*.formatter((value: any) => value?.name)*/
        .required(true)
        .valueType('number')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.CURRENCY)
        .name('salary')
        .label('Salario')
        /*.formatter((value: number) => NumberUtils.formatCurrency(value))*/
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.SWITCH)
        .name('active')
        .label('Activo')
        .formatter(SharedUtils.dxBooleanFormatter)
        .defaultValue(true)
        .build())

    return fields;
}
