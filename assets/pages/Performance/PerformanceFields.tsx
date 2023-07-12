import {Field} from "../../core/field/Field";
import {FieldBuilder} from "../../core/field/FieldBuilder";
import {FieldType} from "../../core/field/FieldType";
import {Constants} from "../../core/common/Constants";
import {SharedUtils} from "../../core/utils/SharedUtils";
import {ColumnCellTemplateData} from "devextreme/ui/data_grid";

export const PerformanceFields = () => {
    const fields: Field[] = [];

    fields.push(new FieldBuilder()
        .isIdentity(true)
        .visible(false)
        .type(FieldType.NONE)
        .valueType('number')
        .name('id')
        .label('Id')
        .required(true)
        .build());

    let filterLine = JSON.stringify({
        'field':'_key',
        'op':'equal',
        'value':'linea'
    });
    fields.push(new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('lines')
        .label('Línea')
        .url(Constants.API_BASE_URL + 'combo-options?filterRules=['+filterLine+']')
        .valueType('number')
        .textField('_value')
        .valueField('id')
        .formatter((cell: ColumnCellTemplateData) => console.log(cell))
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('products')
        .label('Productos')
        .url(Constants.API_BASE_URL + 'products')
        .valueType('string')
        .textField('descripcion')
        .valueField('producto_id')
        .formatter((value: any) => {
            if (value)
            {
                return value['descripcion']
            }
        })
        .width(100)
        .required(true)
        .build());

    let filterType = JSON.stringify({
        'field':'_key',
        'op':'equal',
        'value':'litraje'
    });
    fields.push(new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('types')
        .label('Tipo')
        .url(Constants.API_BASE_URL + 'combo-options?filterRules=['+filterType+']')
        .valueType('number')
        .textField('_value')
        .valueField('id')
        .formatter((value: any) => {
            if (value)
            {
                return value['_value']
            }
        })
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.DATE)
        .name('date')
        .label('Fecha')
        .defaultValue('Today')
        .formatter((cell: ColumnCellTemplateData) => SharedUtils.dateFormatter(cell.value))
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TIME_SPINNER)
        .name('start')
        .label('Hora inicio')
        /*.formatter(SharedUtils.timeFormatter)*/
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TIME_SPINNER)
        .name('end')
        .label('Hora de fin')
        /*.formatter(SharedUtils.timeFormatter)*/
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NONE)
        .name('workedTime')
        .label('Hrs Trab')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NUMBER)
        .valueType('number')
        .precision(3)
        .name('quantity')
        .label('Nro de prod')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NONE)
        .name('nominal')
        .valueType('number')
        .precision(3)
        .label('P.nominal')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NONE)
        .name('real')
        .valueType('number')
        .label('P.real')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NONE)
        .name('efficiency')
        .valueType('number')
        .label('Eficiencia')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXT)
        .name('obs')
        .label('Comentarios')
        .build());

    return fields;
}
