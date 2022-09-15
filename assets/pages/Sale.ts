import DataGrid from "../DataGrid";
import Dialog from "../Dialog";
import Form from "../Form";
import {FormType} from "../FormType";
import {Field} from "../Field";
import {FieldBuilder} from "../FieldBuilder";
import {SharedUtils} from "../SharedUtils";
import {Constants} from "../Constants";

export default class Sale {
    private container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;
    private options: any = {};

    public ROUTES = {
        ADD: 'sale/add',
        EDIT: 'sale/edit/',
        DELETE: 'sale/delete/',
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
            .name('movimiento_id')
            .label('Movimiento id')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('operacion_id')
            .label('Operacion id')
            .visible(false)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('almacen_id')
            .label('Almacen id')
            .visible(false)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('cliente_id')
            .label('Cliente id')
            .visible(false)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.NUMBER)
            .name('tipo_id')
            .label('Tipo id')
            .visible(false)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.DATE)
            .name('fecha')
            .label('Fecha')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.HIDDEN)
            .name('cliente')
            .label('Cliente')
            .width(100)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.NUMBER)
            .name('tipo_pago')
            .label('Pago')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.NUMBER)
            .name('tipo_tarjeta')
            .label('Tipo tarjeta')
            .visible(false)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.HIDDEN)
            .name('numero')
            .label('Voucher')
            .formatter(SharedUtils.voucherFormatter)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.HIDDEN)
            .name('inumero')
            .label('Comprobante')
            .formatter(SharedUtils.invoiceFormatter)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.HIDDEN)
            .name('gravada')
            .label('Total')
            .align('right')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('glosa')
            .label('Glosa')
            .visible(false)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.SWITCH)
            .name('estado')
            .label('Estado')
            .formatter(SharedUtils.invoiceStatusFormatter)
            .align('center')
            .build());
    };

    render() {
        this.renderDataGrid();
        this.renderDialog();
    }

    renderDataGrid() {
        let toolbarButtons = [
            {
                text: Constants.TEXT_ADD,
                iconCls: Constants.ICON_ADD,
                handler: function () {
                    //venta_nuevo(false);
                }
            }, {
                text: 'Vista Previa',
                iconCls: 'fas fa-eye fa-1x text-info',
                handler: function () {
                    //venta_preview();
                }
            }, {
                text: 'Imprimir',
                iconCls: 'fas fa-print fa-1x text-primary',
                handler: function () {
                    //venta_print();
                }
            }, {
                text: 'Anular',
                iconCls: Constants.ICON_DELETE,
                handler: function () {
                    //venta_anular();
                }
            }, {
                text: 'Comprobante',
                iconCls: Constants.ICON_INVOICE,
                handler: function () {
                    //makeInvoice();
                }
            }
        ];
        this.dataGrid = new DataGrid(this.container, this.fields, {
            title: 'Ventas',
            url: 'sale/get',
            routes: {},
            toolbar: toolbarButtons,
            subGrid: {
                url: 'api/sale/detail/',
                columns: [
                    {
                        field: 'producto',
                        title: 'Producto',
                        width: 100
                    }, {
                        field: 'cantidad',
                        title: 'Cantidad',
                        align: 'center'
                    }, {
                        field: 'precio_unitario',
                        title: 'Unitario',
                        align: 'right'
                    }, {
                        field: 'precio_total',
                        title: 'Total',
                        align: 'right'
                    }
                ],
            }
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, this.options);

        this.form = new Form(this.dialog.id, this.fields);
    }
}