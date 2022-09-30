import DataGrid from "../DataGrid";
import Dialog from "../Dialog";
import Form from "../Form";
import {FormType} from "../FormType";
import {Field} from "../Field";
import {FieldBuilder} from "../FieldBuilder";
import {StorageUtils} from "../utils/StorageUtils";
import {ProductDto} from "../dto/product.dto";
import {ProductService} from "../services/product.service";

export default class Purchase {
    private container: string;
    private fields: Field[] = [];
    private detailFields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;
    private options: any = {};
    private productService = new ProductService();

    static readonly ROUTES = {
        ADD: 'purchase/add',
    }

    static readonly EVENTS = {
        ADD: 'purchase:add',
        EDIT: 'purchase:edit',
        DELETE: 'purchase:delete',
        SAVE: 'purchase:save',
        CANCEL: 'purchase:cancel',
        SUCCESS: 'purchase:success',
        LOADING: 'purchase:loading',
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
            .type(FormType.HIDDEN)
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
            .type(FormType.HIDDEN)
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
            .build());
        this.detailFields.push(new FieldBuilder()
            .type(FormType.CURRENCY)
            .name('precio_unitario')
            .label('Precio')
            .build());
        this.detailFields.push(new FieldBuilder()
            .type(FormType.CURRENCY)
            .name('precio_total')
            .label('Total')
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
            title: 'Purchase',
            url: 'purchase/get',
            routes: Purchase.ROUTES,
            events: Purchase.EVENTS,
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
            events: Purchase.EVENTS,
        });

        this.form = new Form(this.dialog.id, this.fields, {
            routes: Purchase.ROUTES,
            events: Purchase.EVENTS,
            detailFields: this.detailFields,
        });
    }

    private onSelectProduct(record, field) {
        let index = field.name.split('-')[1];
        let row = this.form.getRowFormFieldsByIndex(index);
        row['cantidad-' + index].numberbox('setValue', 1);
        row['precio_unitario-' + index].numberbox('setValue', '0.00');
        row['precio_total-' + index].numberbox('setValue', '0.00');
    }
}