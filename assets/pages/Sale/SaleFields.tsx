import {Field} from "../../core/field/Field";
import {FieldBuilder} from "../../core/field/FieldBuilder";
import {FieldType} from "../../core/field/FieldType";
import {Constants} from "../../core/common/Constants";
import {SharedUtils} from "../../core/utils/SharedUtils";
import {StorageUtils} from "../../core/utils/StorageUtils";
import {ColumnCellTemplateData} from "devextreme/ui/data_grid";
import NumberUtils from "../../core/utils/NumberUtils";

export const SaleFields = () => {
    const fields: Field[] = [];

    fields.push(new FieldBuilder()
        .isIdentity(true)
        .visible(false)
        .type(FieldType.NONE)
        .name('movimiento_id')
        .label('Movimiento id')
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.DATE)
        .name('fecha')
        .label('Fecha')
        .defaultValue(new Date())
        .required(true)
        .formatter((cell: ColumnCellTemplateData) => SharedUtils.dateFormatter(cell.value))
        .build());
    let session = StorageUtils.getItem('session');
    let warehouseUrl = `${Constants.API_BASE_URL}branches/${session['branch_id']}/warehouses`;
    fields.push(new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('almacen')
        .label('Almacen')
        .url(warehouseUrl)
        .byKeyUrl(Constants.API_BASE_URL + 'warehouses')
        .valueField('almacen_id')
        .textField('descripcion')
        .visible(false)
        .required(true)
        .defaultValue(session['warehouse_id'])
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('cliente')
        .label('Cliente')
        .width(100)
        .url(Constants.API_BASE_URL + 'customers')
        .mode('remote')
        .valueField('ruc')
        .textField('razon_social')
        .defaultValue('00000000')
        .required(true)
        .formatter((cell: ColumnCellTemplateData) => cell.value?.razon_social)
        .onSelect((record: any) => {
            if (record['price_list']) {
                //this.form.getField('tarifa').combobox('setValue', record['price_list']['id']);
            }
        })
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('tarifa')
        .label('Tarifa')
        .url(Constants.API_BASE_URL + 'price-lists')
        .valueField('id')
        .textField('name')
        .visible(false)
        .onSelect((record: any) => {
            /*bus.emit(Sale.EVENTS.LOADING, true);
            httpClient.get<PriceListItemDto[]>(`price-list/${record.id}/get`)
                .subscribe((response) => {
                    this.priceList = response.data;
                    this.updatePricesByPriceList();
                })
                .add(() => bus.emit(Sale.EVENTS.LOADING, false));*/
        })
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('tipo_pago')
        .label('Método de pago')
        .url(Constants.API_BASE_URL + 'payment-methods')
        .valueField('id')
        .textField('name')
        .valueType('number')
        .defaultValue(1)
        .required(true)
        .formatter((cell: ColumnCellTemplateData) => cell.value?.name)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NONE)
        .name('numero')
        .label('Voucher')
        //.formatter(SharedUtils.voucherFormatter)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NONE)
        .name('invoice')
        .label('Comprobante')
        //.formatter(SharedUtils.invoiceFormatter)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NONE)
        .name('total')
        .label('Total')
        .align('right')
        .formatter((cell: ColumnCellTemplateData) => NumberUtils.formatCurrency(cell.value))
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXTAREA)
        .name('glosa')
        .label('Nota')
        .visible(false)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.CURRENCY)
        .col(6)
        .name('descuento')
        .label('Descuento %')
        .defaultValue(0)
        .readonly(true)
        .onClick((e: any) => {
            /*let total = this.getTotal();
            let discountPercent = this.form.getField('descuento').numberbox('getValue');
            discountPercent = discountPercent ? Number(discountPercent / 100) : 0;
            bus.emit(DiscountDialogEvent.OPEN, {total, discountPercent});*/
        })
        //.onChange(this.calculateGrandTotal.bind(this))
        .visible(false)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.CURRENCY)
        .col(6)
        .name('total')
        .label('Total')
        .visible(false)
        .disabled(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NONE)
        .name('estado')
        .label('Anulado')
        .formatter((cell: ColumnCellTemplateData) => {
            if (cell.value === 1) {
                return <span className="badge rounded-pill bg-success">NO</span>;
            } else if (cell.value === 2) {
                return <span className="badge rounded-pill bg-danger">SI</span>;
            } else {
                return null;
            }
        })
        .align('center')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NUMBER)
        .name('created_by')
        .defaultValue(session['user_id'])
        .visible(false)
        .hidden(true)
        .build());

    return fields;
}

export const SaleDetailFields = () => {
    const fields: Field[] = [];

    fields.push(new FieldBuilder()
        .isIdentity(true)
        .visible(false)
        .type(FieldType.NONE)
        .name('id')
        .label('Id')
        .required(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('producto')
        .label('Producto')
        .width(100)
        .url(Constants.API_BASE_URL + 'products')
        .mode('remote')
        .valueField('producto_id')
        .textField('descripcion')
        //.onSelect((record: any, field: any) => this.onSelectProduct(record, field))
        .formatter((cell: ColumnCellTemplateData) => cell.value?.descripcion)
        .required(true)
        .filterable(false)
        .sortable(false)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.NUMBER)
        .name('cantidad')
        .label('Cantidad')
        .align('center')
        .precision(2)
        //.onChange((newValue: number, oldValue: number, field: any) => this.onQuantityChange(newValue, oldValue, field))
        .required(true)
        .filterable(false)
        .sortable(false)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.CURRENCY)
        .name('precio_unitario')
        .label('Precio')
        .align('right')
        //.onChange((newValue: number, oldValue: number, field: Field) => this.onPriceChange(newValue, oldValue, field))
        .formatter((cell: ColumnCellTemplateData) => NumberUtils.formatCurrency(cell.value))
        .required(true)
        .filterable(false)
        .sortable(false)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.CURRENCY)
        .name('precio_total')
        .label('Total')
        .align('right')
        .readonly(true)
        //.onChange(this.calculateGrandTotal.bind(this))
        .formatter((cell: ColumnCellTemplateData) => NumberUtils.formatCurrency(cell.value))
        .required(true)
        .filterable(false)
        .sortable(false)
        .build());

    return fields;
}
