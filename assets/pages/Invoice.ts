import DataGrid from "../core/datagrid/DataGrid";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {FormType} from "../core/form/FormType";
import {Field} from "../core/form/Field";
import {FieldBuilder} from "../core/form/FieldBuilder";
import {SharedUtils} from "../core/utils/SharedUtils";
import {messenger} from "../core/utils/Messenger";
import {SaleService} from "../services/sale.service";
import {InvoiceService} from "../services/invoice.service";

export default class Invoice {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;
    private saleService: SaleService = new SaleService();
    private invoiceService: InvoiceService = new InvoiceService();

    static readonly ROUTES = {}

    static readonly EVENTS = {
        ADD: 'invoice:add',
        EDIT: 'invoice:edit',
        DELETE: 'invoice:delete',
        SAVE: 'invoice:save',
        CANCEL: 'invoice:cancel',
        SUCCESS: 'invoice:success',
        LOADING: 'invoice:loading',
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
            .name('comprobante_id')
            .label('Comprobante id')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.DATE)
            .name('fecha')
            .label('Fecha')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('cliente_id')
            .label('DNI/RUC')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('cliente')
            .label('Cliente')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('tipo_comprobante')
            .label('Tipo')
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
            .type(FormType.CURRENCY)
            .name('total')
            .label('Total')
            .align('right')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.NONE)
            .name('nota')
            .label('NC')
            .align('center')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.SWITCH)
            .name('estado')
            .label('Anulado')
            .formatter(SharedUtils.voidedFormatter)
            .align('center')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('ticket_baja')
            .label('Ticket baja')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('envio_sunat')
            .label('Envio sunat')
            .formatter(SharedUtils.billStatusFormatter)
            .align('center')
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
        let toolbar = [
            {
                text: 'Enviar a SUNAT',
                iconCls: 'fas fa-cloud-upload fa-1x text-info',
                handler: () => {
                    this.send();
                }
            }, {
                text: 'Enviar por correo',
                iconCls: 'fas fa-envelope fa-1x text-success',
                handler: () => {
                    this.notify();
                }
            }, {
                text: 'Ver',
                iconCls: 'fas fa-eye fa-1x text-primary',
                handler: () => {
                    this.view();
                }
            }, {
                text: 'Consultar CDR',
                iconCls: 'fas fa-search fa-1x text-warning',
                handler: () => {
                    this.consult();
                }
            }
        ];
        this.dataGrid = new DataGrid(this.container, this.fields, {
            title: 'Invoice',
            url: 'invoice/get',
            routes: Invoice.ROUTES,
            events: Invoice.EVENTS,
            toolbar: toolbar,
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, {
            events: Invoice.EVENTS,
        });

        this.form = new Form(this.dialog.id ?? '', this.fields, {
            routes: Invoice.ROUTES,
            events: Invoice.EVENTS,
        });
    }

    send() {
        let selected = this.dataGrid.selectedRow;
        if (!selected) {
            messenger.show('Información', 'Seleccione un registro');
            return;
        }
        if (selected['envio_sunat'] === '0') {
            messenger.show('Información', 'El comprobante ya fue enviado a SUNAT');
            return;
        }
        this.dataGrid.showLoading('Enviando comprobante a SUNAT...');
        this.saleService.makeInvoice(selected['movimiento_id']).subscribe((data) => {
            this.dataGrid.hideLoading();
            messenger.show('Información', 'Comprobante enviado a SUNAT');
            this.dataGrid.reload();
        });
    }

    notify() {
        let selected = this.dataGrid.selectedRow;
        if (!selected) {
            messenger.show('Información', 'Seleccione un registro');
            return;
        }

        this.dataGrid.showLoading('Enviando comprobante por correo...');
        this.invoiceService.sendEmail(selected['movimiento_id']).subscribe((data) => {
            messenger.show('Información', 'Comprobante enviado por correo');
            this.dataGrid.reload();
        }).add(() => this.dataGrid.hideLoading());
    }

    view() {
        let selected = this.dataGrid.selectedRow;
        if (!selected) {
            messenger.show('Información', 'Seleccione un registro');
            return;
        }
        window.open('invoice/' + selected['movimiento_id']);
    }

    consult() {
        let selected = this.dataGrid.selectedRow;
        if (!selected) {
            messenger.show('Información', 'Seleccione un registro');
            return;
        }

        this.dataGrid.showLoading('Consultando CDR...');
        this.invoiceService.consultInvoice(selected['movimiento_id']).subscribe((data) => {
            this.dataGrid.hideLoading();
            messenger.show('Información', 'CDR actualizado');
            this.dataGrid.reload();
        });
    }
}