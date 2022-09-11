import DataGrid from "../DataGrid";
import Dialog from "../Dialog";
import Form from "../Form";
import {FormType} from "../FormType";
import {SharedUtils} from "../SharedUtils";
import {Constants} from "../Constants";
import {Field} from "../Field";
import {FieldBuilder} from "../FieldBuilder";

export default class Supplier {
    private container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;
    private options: any = {};

    public ROUTES = {
        ADD: 'supplier/add',
        EDIT: 'supplier/edit/',
        DELETE: 'supplier/delete/',
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
            .name('proveedor_id')
            .label('Proveedor id')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('ruc')
            .label('Ruc')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('razon_social')
            .label('Razon social')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('domicilio_fiscal')
            .label('Domicilio fiscal')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('telefono')
            .label('Telefono')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.SWITCH)
            .name('estado')
            .label('Estado')
            .build());

    };

    render() {
        this.renderDataGrid();
        this.renderDialog();
    }

    renderDataGrid() {
        this.dataGrid = new DataGrid(this.container, this.fields, {
            title: 'Supplier',
            url: 'supplier/get',
            routes: this.ROUTES,
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, this.options);

        this.form = new Form(this.dialog.id, this.fields);
    }
}