import DataGrid from "../DataGrid";
import Dialog from "../Dialog";
import Form from "../Form";
import {FormType} from "../FormType";
import {Field} from "../Field";
import {FieldBuilder} from "../FieldBuilder";

export default class Cashbox {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;

    static readonly ROUTES = {
        ADD: 'cashbox/add',
        EDIT: 'cashbox/edit/',
        DELETE: 'cashbox/delete/',
    }

    static readonly EVENTS = {
        ADD: 'cashbox:add',
        EDIT: 'cashbox:edit',
        DELETE: 'cashbox:delete',
        SAVE: 'cashbox:save',
        CANCEL: 'cashbox:cancel',
        SUCCESS: 'cashbox:success',
        LOADING: 'cashbox:loading',
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
            .name('caja_id')
            .label('Caja id')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('local_id')
            .label('Local id')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('apertura')
            .label('Apertura')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('cierre')
            .label('Cierre')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.NUMBER)
            .name('usuario_id')
            .label('Usuario id')
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
            title: 'Cashbox',
            url: 'cashbox/get',
            routes: Cashbox.ROUTES,
            events: Cashbox.EVENTS,
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, {
            events: Cashbox.EVENTS,
        });

        this.form = new Form(this.dialog.id, this.fields, {
            routes: Cashbox.ROUTES,
            events: Cashbox.EVENTS,
        });
    }
}