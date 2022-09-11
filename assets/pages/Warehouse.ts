import DataGrid from "../DataGrid";
import Dialog from "../Dialog";
import Form from "../Form";
import {FormType} from "../FormType";
import {SharedUtils} from "../SharedUtils";
import {Field} from "../Field";
import {FieldBuilder} from "../FieldBuilder";

export default class Warehouse {
    private container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;
    private options: any = {};

    public ROUTES = {
        ADD: 'warehouse/add',
        EDIT: 'warehouse/edit/',
        DELETE: 'warehouse/delete/',
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
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.ENTITY)
            .name('local_id')
            .label('Local')
            .required(true)
            .visible(false)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.HIDDEN)
            .name('local')
            .label('Local')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('descripcion')
            .label('Descripcion')
            .width(100)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.NUMBER)
            .name('tipo_id')
            .label('Tipo id')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.SWITCH)
            .name('is_default')
            .label('Is default')
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
            title: 'Almacenes',
            url: 'warehouse/get',
            routes: this.ROUTES,
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, this.options);

        this.form = new Form(this.dialog.id, this.fields);
    }
}