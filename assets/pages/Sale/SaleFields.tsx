import { Field } from '../../core/field/Field';
import { FieldBuilder } from '../../core/field/FieldBuilder';
import { FieldType } from '../../core/field/FieldType';
import { Constants } from '../../core/common/Constants';
import toast, { SharedUtils } from '../../core/utils/SharedUtils';
import { ColumnCellTemplateData } from 'devextreme/ui/data_grid';
import NumberUtils from '../../core/utils/NumberUtils';
import { ProductDto } from '../../dto/product.dto';
import { productService } from '../../service/product.service';
import { GetStockUseCase } from '../../common/GetStockUseCase';
import notify from 'devextreme/ui/notify';
import { DxFormEvents } from '../../core/form/DxForm';
import { httpClient } from '../../core/http/HttpClient';
import { PriceListItemDto } from '../../dto/PriceListItemDto';
import { lastValueFrom } from 'rxjs';
import { DiscountDialogEvent } from '../../components/discount-dialog/DiscountDialog';
import { Setting, settings } from '../../Settings';
import { CustomerPage } from '../Customer/CustomerPage';
import { dispatch } from '../../core/event/EventHook';
import { userInformation } from '../../core/common/UserInformation';

let priceList: PriceListItemDto[] = [];
let selectedWarehouseId = '';

export const SaleFields = (form: DxFormEvents | null) => {
  const fields: Field[] = [];

  fields.push(
    new FieldBuilder()
      .isIdentity(true)
      .visible(false)
      .type(FieldType.NONE)
      .name('id')
      .label('Id')
      .required(true)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.DATE)
      .name('fecha')
      .label('Fecha')
      .defaultValue(new Date())
      .required(true)
      .formatter((cell: ColumnCellTemplateData) => SharedUtils.dateFormatter(cell.value))
      .valueType('date')
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.ENTITY)
      .name('almacen')
      .label('Almacen')
      .url(Constants.API_BASE_URL + 'warehouses')
      .filters([
        {
          field: 'branch',
          operator: '=',
          value: userInformation().currentBranchId,
        },
      ])
      .valueField('id')
      .textField('name')
      .autoSelectIfSingle(true)
      .onChange((e: any) => {
        selectedWarehouseId = e;
      })
      .visible(false)
      .required(true)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.ENTITY)
      .name('cliente')
      .label('Cliente')
      .url(Constants.API_BASE_URL + 'customers')
      .mode('remote')
      .valueField('ruc')
      .textField('businessName')
      .defaultValue('00000000')
      .required(true)
      .formatter((cell: ColumnCellTemplateData) => cell.value?.businessName)
      .onSelect((event: any) => {
        if (event.itemData.price_list) {
          form?.setFieldValue('price_list', event.itemData.price_list.id);
        }
      })
      .buttons([
        {
          name: 'add',
          location: 'first',
          options: {
            icon: 'add',
            stylingMode: 'text',
            onClick: (e: any) => {
              dispatch(CustomerPage.EVENTS.ADD);
            },
          },
        },
      ])
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.ENTITY)
      .name('price_list')
      .label('Tarifa')
      .url(Constants.API_BASE_URL + 'price-lists')
      .valueField('id')
      .textField('name')
      .valueType('number')
      .visible(false)
      .onChange(async (event: any) => {
        priceList = await lastValueFrom(
          httpClient.get<PriceListItemDto[]>(`price-list/${event.value}/get`),
        ).then((response) => response.data);
        const detailData = form?.getDetailData() ?? [];
        detailData.forEach((item: any) => {
          const actualPrice = item.precio_unitario;
          const priceItem = priceList.find((p) => p.id === item.producto.producto_id);
          if (priceItem && priceItem.price !== actualPrice) {
            item.precio_unitario = Number(priceItem.price);
            item.precio_total = item.cantidad * priceItem.price;
            form?.reloadDetailData();
          }
        });
      })
      .build(),
  );
  fields.push(
    new FieldBuilder()
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
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.NONE)
      .name('invoice')
      .label('Comprobante')
      .formatter((cell: ColumnCellTemplateData) => {
        if (cell.value) {
          const url = `/receipt/${cell.data.id}/ticket?format=a4`;
          return (
            <a href={url} className="text-reset" target="_blank" rel="noreferrer">
              <i className="ph ph-file-pdf text-primary" /> {cell.value}
            </a>
          );
        }
        return '';
      })
      .align('center')
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.NONE)
      .name('total')
      .label('Total')
      .align('right')
      .formatter((cell: ColumnCellTemplateData) => NumberUtils.formatCurrency(cell.value))
      .build(),
  );
  fields.push(
    new FieldBuilder().type(FieldType.TEXTAREA).name('glosa').label('Nota').visible(false).build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.CURRENCY)
      .col(6)
      .name('descuento')
      .label('Descuento %')
      .defaultValue(0)
      .readonly(true)
      .onClick((e: any) => {
        const total = form?.getFieldValue('total');
        let discountPercent = form?.getFieldValue('descuento');
        discountPercent = discountPercent ? Number(discountPercent / 100) : 0;
        dispatch(DiscountDialogEvent.OPEN, { total, discountPercent });
      })
      .onChange((e: any) => {
        setTotal(form);
      })
      .visible(false)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.CURRENCY)
      .col(6)
      .name('total')
      .label('Total')
      .visible(false)
      .disabled(true)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.NONE)
      .name('estado')
      .label('Anulado')
      .align('center')
      .formatter((cell: ColumnCellTemplateData) => {
        if (cell.value === 2) {
          return <span className="badge badge-outlined rounded-pill text-bg-danger">Anulado</span>;
        }
        return '';
      })
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.NUMBER)
      .name('created_by')
      .defaultValue(userInformation().currentUserId)
      .visible(false)
      .hidden(true)
      .build(),
  );

  return fields;
};

export const SaleDetailFields = (form: DxFormEvents | null) => {
  const fields: Field[] = [];

  fields.push(
    new FieldBuilder()
      .isIdentity(true)
      .visible(false)
      .type(FieldType.NONE)
      .name('id')
      .label('Id')
      .required(true)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.ENTITY)
      .name('producto')
      .label('Producto')
      .minWidth(300)
      .url(Constants.API_BASE_URL + 'products')
      .mode('remote')
      .valueField('id')
      .textField('name')
      .onChange(async function (this: any, row: any, value: any, currentRow: any) {
        const product = (await productService.getOne(value, true)) as ProductDto;
        let price = product.salePrice;
        if (priceList) {
          const priceItem = priceList.find((item) => item.id === value);
          if (priceItem) {
            price = priceItem.price;
          }
        }
        row.producto = product;
        row.cantidad = 1;
        row.precio_unitario = price;
        row.precio_total = price;
        this.defaultSetCellValue(row, product, currentRow);
      })
      .formatter((cell: ColumnCellTemplateData) => cell.value?.name)
      .required(true)
      .sortable(false)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.NUMBER)
      .name('cantidad')
      .label('Cantidad')
      .align('center')
      .valueType('number')
      .precision(2)
      .onChange(async function (this: any, row: any, value: any, currentRow: any) {
        if (value === '') return;

        const checkStock = await settings.get(Setting.CHECK_STOCK);

        row.precio_total = currentRow.precio_unitario * value;
        if (value > 0 && checkStock?.value == 'true') {
          if (!selectedWarehouseId) {
            toast('Seleccione un almacen para verificar el stock', 'error');
            return;
          }
          const stockData = await GetStockUseCase(currentRow.producto.id, selectedWarehouseId);
          notify(
            {
              message: 'Stock actual: ' + stockData.stock,
              type: 'info',
              duration: 2000,
            },
            { position: 'top right' },
          );
          if (value > stockData.stock) {
            notify(
              {
                message: 'No hay stock suficiente',
                type: 'error',
                duration: 2000,
              },
              { position: 'top right' },
            );
            value = '';
            row.cantidad = '';
            row.precio_total = '';
          }
        }
        this.defaultSetCellValue(row, value, currentRow);
      })
      .required(true)
      .sortable(false)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.CURRENCY)
      .name('precio_unitario')
      .label('Precio')
      .align('right')
      .valueType('number')
      .onChange(async function (this: any, row: any, value: any, currentRow: any) {
        if (value === '') return;
        row.precio_total = currentRow.cantidad * value;
        this.defaultSetCellValue(row, value, currentRow);
      })
      .formatter((cell: ColumnCellTemplateData) => NumberUtils.formatCurrency(cell.value))
      .required(true)
      .sortable(false)
      .valueType('number')
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.CURRENCY)
      .name('precio_total')
      .label('Total')
      .align('right')
      .valueType('number')
      .readonly(true)
      .onChange(async function (this: any, row: any, value: any, currentRow: any) {
        if (value === '') return;
        this.defaultSetCellValue(row, value, currentRow);
        setTotal(form);
      })
      .formatter((cell: ColumnCellTemplateData) => NumberUtils.formatCurrency(cell.value))
      .required(true)
      .filterable(false)
      .sortable(false)
      .build(),
  );

  return fields;
};

const getSubTotal = (form: DxFormEvents | null) => {
  const rows = form?.getDetailData() ?? [];
  let total = 0;
  rows.forEach((row) => {
    total += row.precio_total;
  });
  return total;
};

export const setTotal = (form: DxFormEvents | null) => {
  const subTotal = getSubTotal(form);
  const discountPercent = Number(form?.getFieldValue('descuento') ?? 0);
  const discount = subTotal * (discountPercent / 100);
  const total = Number(subTotal - discount).toFixed(2);

  form?.setFieldValue('total', Number(total));
};
