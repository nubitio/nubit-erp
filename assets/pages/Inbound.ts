import DataGrid from "../core/datagrid/DataGrid";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {FieldType} from "../core/field/FieldType";
import {Field} from "../core/field/Field";
import {FieldBuilder} from "../core/field/FieldBuilder";
import {StorageUtils} from "../core/utils/StorageUtils";
import {ProductService} from "../components/product/product.service";
import {bus} from "../core/event/EventBus";
import NumberUtils from "../core/utils/NumberUtils";

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

    constructor(container: string) {
        this.container = container;
        this.init();
    }

    init() {
        this.fields.push(new FieldBuilder()
            .isIdentity(true)
            .visible(false)
            .type(FieldType.NONE)
            .name('movimiento_id')
            .label('Movimiento id')
            .required(true)
            .visible(false)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.DATE)
            .name('fecha')
            .label('Fecha')
            .defaultValue('Today')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.ENTITY)
            .name('operacion_id')
            .label('Operacion')
            .url('api/operations?filterRules=[{"field":"tipo_id","op":"equal","value":"1"}]')
            .valueField('operacion_id')
            .textField('descripcion')
            .visible(false)
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.NONE)
            .name('operacion')
            .label('Operacion')
            .width(100)
            .build());
        let session = StorageUtils.getItem('session');
        let warehouseUrl = `api/branches/${session['branch_id']}/warehouses`;
        this.fields.push(new FieldBuilder()
            .type(FieldType.ENTITY)
            .name('almacen_id')
            .label('Almacen')
            .url(warehouseUrl)
            .valueField('almacen_id')
            .textField('descripcion')
            .visible(false)
            .required(true)
            .defaultValue(session['warehouse_id'])
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.NONE)
            .name('almacen')
            .label('Almacen')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.ENTITY)
            .name('proveedor_id')
            .label('Proveedor')
            .url('api/suppliers')
            .valueField('proveedor_id')
            .textField('razon_social')
            .visible(false)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXTAREA)
            .name('glosa')
            .label('Glosa')
            .visible(false)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.CURRENCY)
            .name('total')
            .label('Total')
            .visible(false)
            .disabled(true)
            .build());

        this.detailFields.push(new FieldBuilder()
            .type(FieldType.SELECT)
            .valueField('producto_id')
            .textField('descripcion')
            .name('producto_id')
            .label('Producto')
            .width(100)
            .onSelect((record: any, field: Field) => this.onSelectProduct(record, field))
            .formatter((value: any) => value?.descripcion)
            .required(true)
            .visible(false)
            .build());
        this.detailFields.push(new FieldBuilder()
            .type(FieldType.NONE)
            .name('producto')
            .label('Producto')
            .width(100)
            .formatter((value: any) => value?.descripcion)
            .build());
        this.detailFields.push(new FieldBuilder()
            .type(FieldType.NUMBER)
            .name('cantidad')
            .label('Cantidad')
            .align('center')
            .onChange((newValue: number, oldValue: number, field: Field) => this.onQuantityChange(newValue, oldValue, field))
            .required(true)
            .build());
        this.detailFields.push(new FieldBuilder()
            .type(FieldType.CURRENCY)
            .name('precio_unitario')
            .label('P. compra')
            .align('right')
            .onChange((newValue: number, oldValue: number, field: Field) => this.onPriceChange(newValue, oldValue, field))
            .formatter((value: any) => NumberUtils.formatCurrency(value))
            .build());
        this.detailFields.push(new FieldBuilder()
            .type(FieldType.CURRENCY)
            .name('precio_total')
            .label('Total')
            .align('right')
            .onChange(this.calculateGrandTotal.bind(this))
            .formatter((value: any) => NumberUtils.formatCurrency(value))
            .build());
    };

    render() {
        this.productService.getProducts('1').subscribe((response) => {
            this.detailFields.forEach((field) => {
                if (field.name === 'producto_id') {
                    field.data = response.data;
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
            detailUrl: 'api/sales/{id}/details',
            detailFields: this.detailFields
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, {
            width: 900,
            height: 600,
            events: Inbound.EVENTS,
        });

        this.form = new Form(this.dialog.id ?? '', this.fields, {
            routes: Inbound.ROUTES,
            events: Inbound.EVENTS,
            detailFields: this.detailFields,
        });

        bus.on(Form.EVENTS.ROW_REMOVED, () => this.calculateGrandTotal());
    }

    private onSelectProduct(record: any, field: Field) {
        let index = field.name.split('-')[1];
        let row = this.form.getRowFormFieldsByIndex(Number(index));
        row['cantidad-' + index].numberbox('setValue', 1);
        row['precio_unitario-' + index].numberbox('setValue', '0.00');
        row['precio_total-' + index].numberbox('setValue', '0.00');
    }

    private onQuantityChange(newValue: number, oldValue: number, field: Field) {
        let index = field.name.split('-')[1];
        let row = this.form.getRowFormFieldsByIndex(Number(index));
        let price = row['precio_unitario-' + index].numberbox('getValue');
        row['precio_total-' + index].numberbox('setValue', Number(newValue * price).toFixed(2));
    }

    private onPriceChange(newValue: number, oldValue: number, field: Field) {
        let index = field.name.split('-')[1];
        let row = this.form.getRowFormFieldsByIndex(Number(index));
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