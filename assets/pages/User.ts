import DataGrid from "../DataGrid";
import Dialog from "../Dialog";
import Form from "../Form";
import {FormType} from "../FormType";
import {SharedUtils} from "../utils/SharedUtils";
import {Field} from "../Field";
import {FieldBuilder} from "../FieldBuilder";

export default class User {
    private container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;
    private options: any = {};

    public ROUTES = {
        ADD: 'user/add',
        EDIT: 'user/edit/',
        DELETE: 'user/delete/',
    }

    static readonly EVENTS = {
        ADD: 'user:add',
        EDIT: 'user:edit',
        DELETE: 'user:delete',
        SAVE: 'user:save',
        CANCEL: 'user:cancel',
        SUCCESS: 'user:success',
        LOADING: 'user:loading',
    }

    constructor(container, options: any = {}) {
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
            .type(FormType.NONE)
            .name('usuario_id')
            .label('Usuario id')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('username')
            .label('Username')
            .width(100)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.PASSWORD)
            .name('password')
            .label('Password')
            .visible(false)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('pin')
            .label('Pin')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.ENTITY)
            .name('rol_id')
            .label('Rol')
            .url('api/rol')
            .textField('descripcion')
            .valueField('rol_id')
            .visible(false)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.NONE)
            .name('rol')
            .label('Rol')
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
            title: 'Usuarios',
            url: 'user/get',
            routes: this.ROUTES,
            events: User.EVENTS,
        });
    }

    renderDialog() {
        this.options['events'] = User.EVENTS;
        this.dialog = new Dialog(this.container, this.options);

        this.form = new Form(this.dialog.id, this.fields, {
            routes: this.ROUTES,
            events: User.EVENTS
        });
    }
}