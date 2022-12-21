import DataGrid from "../DataGrid";
import Dialog from "../Dialog";
import Form from "../Form";
import {FormType} from "../FormType";
import {Field} from "../Field";
import {FieldBuilder} from "../FieldBuilder";
import {StorageUtils} from "../utils/StorageUtils";
import {ProductDto} from "../dto/product.dto";
import {ProductService} from "../services/product.service";
import {bus} from "../EventBus";
import {Event} from "../Event";

export default class Inbound {
    private readonly container: string;
    private fields: Field[] = [];
    private detailFields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;
    private productService = new ProductService();

    static readonly ROUTES = {
        ADD: 'inbound/add',
    }

    static readonly EVENTS = {
        ADD: 'inbound:add',
        EDIT: 'inbound:edit',
        DELETE: 'inbound:delete',
        SAVE: 'inbound:save',
        CANCEL: 'inbound:cancel',
        SUCCESS: 'inbound:success',
        LOADING: 'inbound:loading',
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
            .name('movimiento_id')
            .label('Movimiento id')
            .required(true)
            .visible(false)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.DATE)
            .name('fecha')
            .label('Fecha')
            .defaultValue('Today')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.ENTITY)
            .name('operacion_id')
            .label('Operacion')
            .url('api/operation/1')
            .valueField('operacion_id')
            .textField('descripcion')
            .visible(false)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.NONE)
            .name('operacion')
            .label('Operacion')
            .width(100)
            .build());
        let session = StorageUtils.getItem('session');
        let localId = session['local_id'];
        let warehouseUrl = `api/local/${localId}/warehouse`;
        this.fields.push(new FieldBuilder()
            .type(FormType.ENTITY)
            .name('almacen_id')
            .label('Almacen')
            .url(warehouseUrl)
            .valueField('almacen_id')
            .textField('descripcion')
            .visible(false)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.NONE)
            .name('almacen')
            .label('Almacen')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.ENTITY)
            .name('proveedor_id')
            .label('Proveedor')
            .url('api/supplier')
            .valueField('proveedor_id')
            .textField('razon_social')
            .visible(false)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXTAREA)
            .name('glosa')
            .label('Glosa')
            .visible(false)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.CURRENCY)
            .name('total')
            .label('Total')
            .visible(false)
            .disabled(true)
            .build());

        this.detailFields.push(new FieldBuilder()
            .type(FormType.SELECT)
            .valueField('producto_id')
            .textField('descripcion')
            .name('producto_id')
            .label('Producto')
            .onSelect((record, field) => this.onSelectProduct(record, field))
            .build());
        this.detailFields.push(new FieldBuilder()
            .type(FormType.NUMBER)
            .name('cantidad')
            .label('Cantidad')
            .onChange((newValue, oldValue, field) => this.onQuantityChange(newValue, oldValue, field))
            .build());
        this.detailFields.push(new FieldBuilder()
            .type(FormType.CURRENCY)
            .name('precio_unitario')
            .label('P. compra')
            .onChange((newValue, oldValue, field) => this.onPriceChange(newValue, oldValue, field))
            .build());
        this.detailFields.push(new FieldBuilder()
            .type(FormType.CURRENCY)
            .name('precio_total')
            .label('Total')
            .onChange(this.calculateGrandTotal.bind(this))
            .build());
    };

    render() {
        this.productService.getProducts('1').subscribe((data: ProductDto[]) => {
            this.detailFields.forEach((field) => {
                if (field.name === 'producto_id') {
                    field.data = data;
                }
            });
            this.renderDataGrid();
            this.renderDialog();
        });
    }

    renderDataGrid() {
        this.dataGrid = new DataGrid(this.container, this.fields, {
            title: 'Inbound',
            url: 'inbound/get',
            routes: Inbound.ROUTES,
            events: Inbound.EVENTS,
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
        this.dialog = new Dialog(this.container, {
            width: 900,
            height: 600,
            events: Inbound.EVENTS,
        });

        this.form = new Form(this.dialog.id, this.fields, {
            routes: Inbound.ROUTES,
            events: Inbound.EVENTS,
            detailFields: this.detailFields,
        });

        bus.on(
            action => action.name == Form.EVENTS.ROW_REMOVED,
            (action: Event) => {
                this.calculateGrandTotal();
            }
        );
    }

    private onSelectProduct(record, field) {
        let index = field.name.split('-')[1];
        let row = this.form.getRowFormFieldsByIndex(index);
        row['cantidad-' + index].numberbox('setValue', 1);
        row['precio_unitario-' + index].numberbox('setValue', '0.00');
        row['precio_total-' + index].numberbox('setValue', '0.00');
    }

    private onQuantityChange(newValue, oldValue, field) {
        let index = field.name.split('-')[1];
        let row = this.form.getRowFormFieldsByIndex(index);
        let price = row['precio_unitario-' + index].numberbox('getValue');
        row['precio_total-' + index].numberbox('setValue', Number(newValue * price).toFixed(2));
    }

    private onPriceChange(newValue, oldValue, field) {
        let index = field.name.split('-')[1];
        let row = this.form.getRowFormFieldsByIndex(index);
        let quantity = row['cantidad-' + index].numberbox('getValue');
        row['precio_total-' + index].numberbox('setValue', Number(newValue * quantity).toFixed(2));
    }

    private calculateGrandTotal() {
        let total = 0;
        let formFields = this.form.getDetailFormFields();
        for (let key in formFields) {
            if (key.startsWith('precio_total-')) {
                total += Number(formFields[key].numberbox('getValue'));
            }
        }
        this.form.getField('total').numberbox('setValue', total);
    }
}