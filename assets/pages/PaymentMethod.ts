import DataGrid from "../core/datagrid/DataGrid";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {FormType} from "../core/form/FormType";
import {Field} from "../core/form/Field";
import {FieldBuilder} from "../core/form/FieldBuilder";

export default class PaymentMethod {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;

    static readonly API_URL = 'api/paymentMethod';

    static readonly EVENTS = {
        ADD: 'paymentMethod:add',
        EDIT: 'paymentMethod:edit',
        DELETE: 'paymentMethod:delete',
        SAVE: 'paymentMethod:save',
        CANCEL: 'paymentMethod:cancel',
        SUCCESS: 'paymentMethod:success',
        LOADING: 'paymentMethod:loading',
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
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.SWITCH)
            .name('active')
            .label('Active')
            .build())
    };

    render() {
        this.renderDataGrid();
        this.renderDialog();
    }

    renderDataGrid() {
        this.dataGrid = new DataGrid(this.container, this.fields, {
            title: 'PaymentMethod',
            url: PaymentMethod.API_URL,
            method: 'get',
            allowAdd: true,
            allowEdit: true,
            allowDelete: true,
            events: PaymentMethod.EVENTS,
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, {
            events: PaymentMethod.EVENTS,
        });

        this.form = new Form(this.dialog.id, this.fields, {
            url: PaymentMethod.API_URL,
            events: PaymentMethod.EVENTS,
        });
    }
}