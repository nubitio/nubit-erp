import DataGrid from "../DataGrid";
import Dialog from "../Dialog";
import Form from "../Form";
import {FormType} from "../FormType";
import {Field} from "../Field";
import {FieldBuilder} from "../FieldBuilder";

export default class Invoice {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;

    static readonly ROUTES = {
        ADD: 'invoice/add',
        EDIT: 'invoice/edit/',
        DELETE: 'invoice/delete/',
    }

    static readonly EVENTS = {
        ADD: 'invoice:add',
        EDIT: 'invoice:edit',
        DELETE: 'invoice:delete',
        SAVE: 'invoice:save',
        CANCEL: 'invoice:cancel',
        SUCCESS: 'invoice:success',
        LOADING: 'invoice:loading',
    }

    constructor(container) {
        this.container = container;
        this.init();
    }

    init() {
        this.fields.push(new FieldBuilder()
            .isIdentity(true)
            .visible(false)
            .type(FormType.NONE)
            .name('comprobante_id')
            .label('Comprobante id')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('movimiento_id')
            .label('Movimiento id')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('cliente_id')
            .label('Cliente id')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('tipo_comprobante_id')
            .label('Tipo comprobante id')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.DATE)
            .name('fecha')
            .label('Fecha')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('serie')
            .label('Serie')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('numero')
            .label('Numero')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.SWITCH)
            .name('inafecto')
            .label('Inafecto')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('subtotal')
            .label('Subtotal')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('gravada')
            .label('Gravada')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('igv')
            .label('Igv')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('rc')
            .label('Rc')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('total')
            .label('Total')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.NUMBER)
            .name('discount_percent')
            .label('Discount percent')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('discount_amount')
            .label('Discount amount')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.SWITCH)
            .name('voided')
            .label('Voided')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('digest')
            .label('Digest')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('ticket_baja')
            .label('Ticket baja')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('correlativo_baja')
            .label('Correlativo baja')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('envio_sunat')
            .label('Envio sunat')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('mensaje_sunat')
            .label('Mensaje sunat')
            .build());

    };

    render() {
        this.renderDataGrid();
        this.renderDialog();
    }

    renderDataGrid() {
        this.dataGrid = new DataGrid(this.container, this.fields, {
            title: 'Invoice',
            url: 'invoice/get',
            routes: Invoice.ROUTES,
            events: Invoice.EVENTS,
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, {
            events: Invoice.EVENTS,
        });

        this.form = new Form(this.dialog.id, this.fields, {
            routes: Invoice.ROUTES,
            events: Invoice.EVENTS,
        });
    }
}