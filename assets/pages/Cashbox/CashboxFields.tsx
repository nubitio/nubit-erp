import {Field} from "../../core/field/Field";
import {FieldBuilder} from "../../core/field/FieldBuilder";
import {FieldType} from "../../core/field/FieldType";
import {SharedUtils} from "../../core/utils/SharedUtils";
import {ColumnCellTemplateData} from "devextreme/ui/data_grid";
import NumberUtils from "../../core/utils/NumberUtils";
import {Constants} from "../../core/common/Constants";

export const CashboxFields = () => {
    const fields: Field[] = [];

    fields.push(new FieldBuilder()
        .isIdentity(true)
        .visible(false)
        .type(FieldType.NONE)
        .name('caja_id')
        .label('Caja id')
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('local_id')
        .label('Local id')
        .visible(false)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('apertura')
        .label('Apertura')
        .formatter((cell: ColumnCellTemplateData) => SharedUtils.dateTimeFormatter(cell.value))
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('cierre')
        .label('Cierre')
        .formatter((cell: ColumnCellTemplateData) => SharedUtils.dateTimeFormatter(cell.value))
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('usuario')
        .label('Usuario')
        .url(Constants.API_BASE_URL + 'users')
        .valueField('usuario_id')
        .textField('username')
        .align('center')
        .formatter((cell: ColumnCellTemplateData) => cell.data.usuario.username)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NONE)
        .name('balance')
        .label('Balance')
        .align('right')
        .formatter((cell: ColumnCellTemplateData) => NumberUtils.formatCurrency(cell.value))
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.SWITCH)
        .name('estado')
        .label('Estado')
        .align('center')
        .formatter((cell: ColumnCellTemplateData) => {
            if (Number(cell.value) === 1) {
                return <span className="badge rounded-pill bg-success">ACTIVO</span>;
            } else if (Number(cell.value) === 2) {
                return <span className="badge rounded-pill bg-secondary">CERRADO</span>;
            }
        })
        .build());

    return fields;
}

export const CashboxDetailFields = (cashboxId: string | null = null) => {
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
        .type(FieldType.TEXT)
        .name('caja_id')
        .label('Caja id')
        .visible(false)
        .hidden(true)
        .defaultValue(cashboxId ?? '')
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NONE)
        .name('uid')
        .label('Uid')
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENUM)
        .name('tipo_id')
        .label('Tipo')
        .data([
            {value: 1, text: 'Ingreso'},
            {value: 2, text: 'Egreso'},
        ])
        .formatter((cell: ColumnCellTemplateData) => {
            if (Number(cell.value) === 1) {
                return <span className="text-success"><i className="fas fa-arrow-circle-up"></i> Ingreso</span>;
            } else if (Number(cell.value) === 2) {
                return <span className="text-danger"><i className="fas fa-arrow-circle-down"></i> Egreso</span>;
            }
        })
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('descripcion')
        .label('Descripcion')
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('referencia')
        .label('Referencia')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.CURRENCY)
        .name('monto')
        .label('Monto')
        .align('right')
        .formatter((cell: ColumnCellTemplateData) => NumberUtils.formatCurrency(cell.value))
        .required(true)
        .build());

    return fields;
}
