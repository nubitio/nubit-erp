import DataGrid from "../DataGrid";
import Dialog from "../Dialog";
import Form from "../Form";
import {FormType} from "../FormType";
import {SharedUtils} from "../utils/SharedUtils";
import {Field} from "../Field";
import {FieldBuilder} from "../FieldBuilder";

export default class Local {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;

    static readonly ROUTES = {
        ADD: 'local/add',
        EDIT: 'local/edit/',
        DELETE: 'local/delete/',
    }

    static readonly EVENTS = {
        ADD: 'local:add',
        EDIT: 'local:edit',
        DELETE: 'local:delete',
        SAVE: 'local:save',
        CANCEL: 'local:cancel',
        SUCCESS: 'local:success',
        LOADING: 'local:loading',
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
            .name('local_id')
            .label('Local id')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.ENTITY)
            .name('empresa_id')
            .label('Empresa')
            .url('api/company')
            .valueField('empresa_id')
            .textField('razon_social')
            .visible(false)
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.HIDDEN)
            .name('empresa')
            .label('Empresa')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('descripcion')
            .label('Descripcion')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('direccion')
            .label('Direccion')
            .width(100)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('telefono')
            .label('Telefono')
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
            title: 'Locales',
            url: 'local/get',
            routes: Local.ROUTES,
            events: Local.EVENTS,
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, {
            events: Local.EVENTS,
        });

        this.form = new Form(this.dialog.id, this.fields, {
            routes: Local.ROUTES,
            events: Local.EVENTS,
        });
    }
}