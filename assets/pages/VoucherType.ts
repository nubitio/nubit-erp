import DataGrid from "../DataGrid";
import Dialog from "../Dialog";
import Form from "../Form";
import {FormType} from "../FormType";
import {Field} from "../Field";
import {FieldBuilder} from "../FieldBuilder";
import {SharedUtils} from "../utils/SharedUtils";

export default class VoucherType {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;

    static readonly ROUTES = {
        ADD: 'voucher-type/add',
        EDIT: 'voucher-type/edit/',
        DELETE: 'voucher-type/delete/',
    }

    static readonly EVENTS = {
        ADD: 'voucher-type:add',
        EDIT: 'voucher-type:edit',
        DELETE: 'voucher-type:delete',
        SAVE: 'voucher-type:save',
        CANCEL: 'voucher-type:cancel',
        SUCCESS: 'voucher-type:success',
        LOADING: 'voucher-type:loading',
    }

    constructor(container) {
        this.container = container;
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
            title: 'VoucherType',
            url: 'voucher-type/get',
            routes: VoucherType.ROUTES,
            events: VoucherType.EVENTS,
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, {
            events: VoucherType.EVENTS,
        });

        this.form = new Form(this.dialog.id, this.fields, {
            routes: VoucherType.ROUTES,
            events: VoucherType.EVENTS,
        });
    }
}