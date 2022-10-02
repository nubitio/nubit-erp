import DataGrid from "../DataGrid";
import Dialog from "../Dialog";
import Form from "../Form";
import {FormType} from "../FormType";
import {Field} from "../Field";
import {FieldBuilder} from "../FieldBuilder";
import {SharedUtils} from "../utils/SharedUtils";

export default class Warehouse {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;

    static readonly ROUTES = {
        ADD: 'warehouse/add',
        EDIT: 'warehouse/edit/',
        DELETE: 'warehouse/delete/',
    }

    static readonly EVENTS = {
        ADD: 'warehouse:add',
        EDIT: 'warehouse:edit',
        DELETE: 'warehouse:delete',
        SAVE: 'warehouse:save',
        CANCEL: 'warehouse:cancel',
        SUCCESS: 'warehouse:success',
        LOADING: 'warehouse:loading',
    }

    constructor(container) {
        this.container = container;
        this.init();
    }

    init() {
        this.fields.push(new FieldBuilder()
            .isIdentity(true)
            .visible(false)
            .type(FormType.HIDDEN)
            .name('almacen_id')
            .label('Almacen id')
            .required(true)
            .visible(false)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.ENTITY)
            .name('local_id')
            .label('Local')
            .url('api/local')
            .valueField('local_id')
            .textField('descripcion')
            .required(true)
            .visible(false)
            .build());

        this.fields.push(new FieldBuilder()
            .type(FormType.HIDDEN)
            .name('local')
            .label('Local')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('descripcion')
            .label('Descripcion')
            .width(100)
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.SWITCH)
            .name('estado')
            .label('Activo')
            .formatter(SharedUtils.activeFormatter)
            .defaultValue(true)
            .build());
    };

    render() {
        this.renderDataGrid();
        this.renderDialog();
    }

    renderDataGrid() {
        this.dataGrid = new DataGrid(this.container, this.fields, {
            title: 'Warehouse',
            url: 'warehouse/get',
            routes: Warehouse.ROUTES,
            events: Warehouse.EVENTS,
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, {
            events: Warehouse.EVENTS,
        });

        this.form = new Form(this.dialog.id, this.fields, {
            routes: Warehouse.ROUTES,
            events: Warehouse.EVENTS,
        });
    }
}