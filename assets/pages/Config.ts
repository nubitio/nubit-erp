import DataGrid from "../core/datagrid/DataGrid";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {FormType} from "../core/form/FormType";
import {Field} from "../core/form/Field";
import {FieldBuilder} from "../core/form/FieldBuilder";

export default class Config {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;

    static readonly ROUTES = {
        ADD: 'config/add',
        EDIT: 'config/edit/',
        DELETE: 'config/delete/',
    }

    static readonly EVENTS = {
        ADD: 'config:add',
        EDIT: 'config:edit',
        DELETE: 'config:delete',
        SAVE: 'config:save',
        CANCEL: 'config:cancel',
        SUCCESS: 'config:success',
        LOADING: 'config:loading',
    }

    constructor(container: string) {
        this.container = container;
        this.init();
    }

    init() {
        this.fields.push(new FieldBuilder()
            .isIdentity(true)
            .visible(false)
            .type(FormType.NONE)
            .name('id')
            .label('Id')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('name')
            .label('Name')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('value')
            .label('Value')
            .width(100)
            .required(true)
            .build());
    };

    render() {
        this.renderDataGrid();
        this.renderDialog();
    }

    renderDataGrid() {
        this.dataGrid = new DataGrid(this.container, this.fields, {
            title: 'Config',
            url: 'config/get',
            routes: Config.ROUTES,
            events: Config.EVENTS,
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, {
            events: Config.EVENTS,
        });

        this.form = new Form(this.dialog.id ?? '', this.fields, {
            routes: Config.ROUTES,
            events: Config.EVENTS,
        });
    }
}