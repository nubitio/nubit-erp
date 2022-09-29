import DataGrid from "../DataGrid";
import Dialog from "../Dialog";
import Form from "../Form";
import {FormType} from "../FormType";
import {Field} from "../Field";
import {FieldBuilder} from "../FieldBuilder";
import {SharedUtils} from "../utils/SharedUtils";

export default class Warehouse {
    private container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;
    private options: any = {};

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

    constructor(container, options) {
        this.container = container;
        this.dataGrid = null;
        this.dialog = null;
        this.form = null;
        this.options = options;
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
            .type(FormType.TEXT)
            .name('local_id')
            .label('Local id')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('descripcion')
            .label('Descripcion')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.NUMBER)
            .name('tipo_id')
            .label('Tipo id')
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