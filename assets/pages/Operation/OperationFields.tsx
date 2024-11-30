import {Field} from "../../core/field/Field";
import {FieldBuilder} from "../../core/field/FieldBuilder";
import {FieldType} from "../../core/field/FieldType";
import {SharedUtils} from "../../core/utils/SharedUtils";
import {ColumnCellTemplateData} from "devextreme/ui/data_grid";

export const OperationFields = () => {
    const fields: Field[] = [];

    fields.push(new FieldBuilder()
        .isIdentity(true)
        .visible(false)
        .type(FieldType.NONE)
        .name('operacion_id')
        .label('Operacion id')
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('descripcion')
        .label('Descripcion')
        .width('100%')
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENUM)
        .name('tipo_id')
        .label('Tipo')
        .align('center')
        .data([
            {value: 1, text: 'Entrada'},
            {value: 2, text: 'Salida'},
        ])
        .formatter((cell: ColumnCellTemplateData) => {
            if (cell.value === 1) {
                return <span className="text-success"><i className="fas fa-arrow-down"/> Entrada</span>;
            } else if (cell.value === 2) {
                return <span className="text-danger"><i className="fas fa-arrow-up"/> Salida</span>;
            }
        })
        .required(true)
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
