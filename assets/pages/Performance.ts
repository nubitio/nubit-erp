import DataGrid from "../core/datagrid/DataGrid";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {FieldType} from "../core/field/FieldType";
import {Field} from "../core/field/Field";
import {FieldBuilder} from "../core/field/FieldBuilder";

export default class Performance {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;

    static readonly API_URL = 'api/performances';

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
        this.fields.push(new FieldBuilder()
            .type(FieldType.NUMBER)
            .name('line_fk')
            .label('Line fk')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('product_fk')
            .label('Product fk')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.NUMBER)
            .name('type_fk')
            .label('Type fk')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.DATE)
            .name('date')
            .label('Date')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TIME)
            .name('start')
            .label('Start')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TIME)
            .name('end')
            .label('End')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.NUMBER)
            .valueType('float')
            .precision(3)
            .name('quantity')
            .label('Quantity')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('obs')
            .label('Obs')
            .build())
    };

    render() {
        this.renderDataGrid();
        this.renderDialog();
    }

    renderDataGrid() {
        this.dataGrid = new DataGrid(this.container, this.fields, {
            title: 'Performance',
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