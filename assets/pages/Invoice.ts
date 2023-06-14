import DataGrid from "../core/datagrid/DataGrid";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {FieldType} from "../core/field/FieldType";
import {Field} from "../core/field/Field";
import {FieldBuilder} from "../core/field/FieldBuilder";
import {SharedUtils} from "../core/utils/SharedUtils";
import {messenger} from "../core/utils/Messenger";
import {InvoiceService} from "../services/invoice.service";
import NumberUtils from "../core/utils/NumberUtils";
import {Constants} from "../core/common/Constants";

export default class Invoice {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;
    private invoiceService: InvoiceService = new InvoiceService();

    static readonly API_URL = Constants.API_BASE_URL + 'invoices';

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
            .type(FieldType.NONE)
            .name('comprobante_id')
            .label('Comprobante id')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.DATE)
            .name('fecha')
            .label('Fecha')
            .formatter(SharedUtils.dateFormatter)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('cliente_id')
            .label('DNI/RUC')
            .formatter((value: any, row: any) => row['cliente']?.['ruc'])
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('c.razon_social')
            .label('Cliente')
            .formatter((value: any, row: any) => row['cliente']?.['razon_social'])
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('tipo_comprobante_id')
            .label('Tipo')
            .formatter((value: any) => value === '01' ? 'Factura' : 'Boleta')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('serie')
            .label('Serie')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('numero')
            .label('Número')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.CURRENCY)
            .name('total')
            .label('Total')
            .align('right')
            .formatter((value: number) => NumberUtils.formatCurrency(value))
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.NONE)
            .name('nota')
            .label('NC')
            .align('center')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.SWITCH)
            .name('voided')
            .label('Anulado')
            .formatter(SharedUtils.voidedFormatter)
            .align('center')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('ticket_baja')
            .label('Ticket baja')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('envio_sunat')
            .label('Envio sunat')
            .formatter(SharedUtils.billStatusFormatter)
            .align('center')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
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
            url: Invoice.API_URL,
            method: 'get',
            events: Invoice.EVENTS,
            toolbar: toolbar,
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, {
            events: Invoice.EVENTS,
        });

        this.form = new Form(this.dialog.id ?? '', this.fields, {
            url: Invoice.API_URL,
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
        this.invoiceService.sendInvoice(selected['movimiento_id']).subscribe((response) => {
            messenger.show('Información', response.data.message);
            this.dataGrid.reload();
        }).add(() => this.dataGrid.hideLoading());
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
        this.invoiceService.consultInvoice(selected['movimiento_id']).subscribe((response) => {
            messenger.show('Información', response.data.message);
            this.dataGrid.reload();
        }).add(() => this.dataGrid.hideLoading());
    }
}
