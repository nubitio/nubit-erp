import DataGrid from "../DataGrid";
import Dialog from "../Dialog";
import Form from "../Form";
import {FormType} from "../FormType";
import {SharedUtils} from "../SharedUtils";
import {Constants} from "../Constants";
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
        ADD: 'user/add/',
        EDIT: 'user/edit/',
        DELETE: 'user/delete/',
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
            .type(FormType.NUMBER)
            .isIdentity(true)
            .name('usuario_id')
            .label('Usuario id')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('username')
            .label('Username')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('password')
            .label('Password')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.NUMBER)
            .name('estado')
            .label('Estado')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('date_created')
            .label('Date created')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('date_updated')
            .label('Date updated')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('pin')
            .label('Pin')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.NUMBER)
            .name('rol_id')
            .label('Rol id')
            .build());

    };

    render() {
        this.renderDataGrid();
        this.renderDialog();
    }

    renderDataGrid() {
        this.dataGrid = new DataGrid(this.container, this.fields, {
            title: 'User',
            url: 'user/get',
            routes: this.ROUTES,
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, this.options);

        this.form = new Form(this.dialog.id, this.fields);
    }
}