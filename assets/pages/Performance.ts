import DataGrid from "../core/datagrid/DataGrid";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {FieldType} from "../core/field/FieldType";
import {Field} from "../core/field/Field";
import {FieldBuilder} from "../core/field/FieldBuilder";
import {SharedUtils} from "../core/utils/SharedUtils";
import {Constants} from "../core/common/Constants";

export default class Performance {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;

    static readonly API_URL = Constants.API_BASE_URL + 'performances';

    static readonly EVENTS = {
        ADD: 'performance:add',
        EDIT: 'performance:edit',
        DELETE: 'performance:delete',
        SAVE: 'performance:save',
        CANCEL: 'performance:cancel',
        SUCCESS: 'performance:success',
        LOADING: 'performance:loading',
    }

    constructor(container: string) {
        this.container = container;
        this.init();
    }

    init() {
        this.fields.push(new FieldBuilder()
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
        this.fields.push(new FieldBuilder()
            .type(FieldType.ENTITY)
            .name('lines')
            .label('Línea')
            .url(Constants.API_BASE_URL + 'combo-options?filterRules=['+filterLine+']')
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
        this.fields.push(new FieldBuilder()
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
        this.fields.push(new FieldBuilder()
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
        this.fields.push(new FieldBuilder()
            .type(FieldType.DATE)
            .name('date')
            .label('Fecha')
            .defaultValue('Today')
            .formatter(SharedUtils.dateFormatter)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TIME_SPINNER)
            .name('start')
            .label('Hora inicio')
            .formatter(SharedUtils.timeFormatter)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TIME_SPINNER)
            .name('end')
            .label('Hora de fin')
            .formatter(SharedUtils.timeFormatter)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.NONE)
            .name('workedTime')
            .label('Hrs Trab')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.NUMBER)
            .valueType('float')
            .precision(3)
            .name('quantity')
            .label('Nro de prod')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.NONE)
            .name('nominal')
            .valueType('float')
            .precision(3)
            .label('P.nominal')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.NONE)
            .name('real')
            .valueType('float')
            .label('P.real')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.NONE)
            .name('efficiency')
            .valueType('number')
            .label('Eficiencia')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('obs')
            .label('Comentarios')
            .build());
    };

    render() {
        this.renderDataGrid();
        this.renderDialog();
    }

    renderDataGrid() {
        this.dataGrid = new DataGrid(this.container, this.fields, {
            title: 'Módulo de rendimiento',
            url: Performance.API_URL,
            method: 'get',
            allowAdd: true,
            allowEdit: true,
            allowDelete: true,
            events: Performance.EVENTS,
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, {
            events: Performance.EVENTS,
        });

        this.form = new Form(this.dialog.id, this.fields, {
            url: Performance.API_URL,
            events: Performance.EVENTS,
        });
    }
}
