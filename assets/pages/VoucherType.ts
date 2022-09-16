import DataGrid from "../DataGrid";
import Dialog from "../Dialog";
import Form from "../Form";
import {FormType} from "../FormType";
import {SharedUtils} from "../utils/SharedUtils";
import {Field} from "../Field";
import {FieldBuilder} from "../FieldBuilder";

export default class VoucherType {
    private container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;
    private options: any = {};

    public ROUTES = {
        ADD: 'vouchertype/add',
        EDIT: 'vouchertype/edit/',
        DELETE: 'vouchertype/delete/',
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
            .type(FormType.TEXT)
            .name('codigo')
            .label('Codigo')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('descripcion')
            .label('Descripcion')
            .width(100)
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
            title: 'Tipos de comprobante',
            url: 'vouchertype/get',
            routes: this.ROUTES,
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, this.options);

        this.form = new Form(this.dialog.id, this.fields);
    }
}