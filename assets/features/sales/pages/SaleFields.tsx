import { Constants } from '@platform/common/Constants';
import { DxFormEvents } from '@platform/form/DxForm';
import { Field } from '@platform/field/Field';
import {
  identityField,
  entityField,
  currencyField,
  dateField,
  numberField,
  checkboxField,
  selectField,
  textareaField,
  noneField,
} from '@platform/field/FieldBuilders';
import { userInformation } from '@platform/common/UserInformation';
import toast, { SharedUtils } from '@shared/lib/SharedUtils';
import NumberUtils from '@shared/lib/NumberUtils';
import { httpClient } from '@shared/api/HttpClient';
import { productService } from '@features/product';
import { GetStockUseCase } from '@assets/common/GetStockUseCase';
import { ProductDto } from '@dto/product.dto';
import { PriceListItemDto } from '@dto/PriceListItemDto';
import { Setting, settings } from '@assets/Settings';
import { ColumnCellTemplateData } from 'devextreme/ui/data_grid';
import { lastValueFrom } from 'rxjs';
import { CustomerPage } from '@features/catalog/pages/customers';

let priceList: PriceListItemDto[] = [];
let selectedWarehouseId = '';

export type SaleFieldCallbacks = {
  onOpenAddCustomer?: () => void;
  onOpenDiscount?: (params: { total: number; discountPercent: number }) => void;
};

export const SaleFields = (form: DxFormEvents | null, callbacks?: SaleFieldCallbacks) => {
  const fields: Field[] = [];

  fields.push(identityField().build());
  fields.push(
    checkboxField()
      .name('emitInvoice')
      .label('Emitir comprobante')
      .valueType('boolean')
      .defaultValue(() => settings.getCached(Setting.AUTO_INVOICE)?.value === 'true')
      .visibleOnForm(true)
      .visible(false)
      .build(),
  );
  fields.push(
    dateField()
      .name('issuedAt')
      .label('Fecha')
      .required(true)
      .formatter((cell: ColumnCellTemplateData) => SharedUtils.dateFormatter(cell.value))
      .valueType('date')
      .build(),
  );
  fields.push(
    entityField(Constants.API_BASE_URL + 'warehouses', 'id', 'name')
      .name('warehouse')
      .label('Almacen')
      .filters([
        {
          field: 'branch',
          operator: '=',
          value: userInformation().currentBranchId,
        },
      ])
      .autoSelectIfSingle(true)
      .onChange((e: any) => {
        selectedWarehouseId = e;
      })
      .visible(false)
      .required(true)
      .build(),
  );
  fields.push(
    entityField(Constants.API_BASE_URL + 'customers', 'id', 'businessName')
      .name('customer')
      .label('Cliente')
      .mode('remote')
      .defaultValue(1)
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
            onClick: (_e: any) => {
              callbacks?.onOpenAddCustomer?.();
            },
          },
        },
      ])
      .build(),
  );
  fields.push(
    entityField(Constants.API_BASE_URL + 'price-lists', 'id', 'name')
      .name('price_list')
      .label('Tarifa')
      .valueType('number')
      .visible(false)
      .onChange(async (event: any) => {
        priceList = await lastValueFrom(
          httpClient.get<PriceListItemDto[]>(`price-list/${event.value}/get`),
        ).then((response) => response.data);
        const detailData = form?.getDetailData() ?? [];
        detailData.forEach((item: any) => {
          const actualPrice = item.unitPrice;
          const priceItem = priceList.find(
            (p) => p.id === (item.product?.id ?? item.product?.producto_id),
          );
          if (priceItem && priceItem.price !== actualPrice) {
            item.unitPrice = Number(priceItem.price);
            item.netAmount = item.qty * priceItem.price;
            form?.reloadDetailData();
          }
        });
      })
      .build(),
  );
  fields.push(
    entityField(Constants.API_BASE_URL + 'payment-methods', 'id', 'name')
      .name('paymentMethod')
      .label('Método de pago')
      .valueType('number')
      .defaultValue(1)
      .required(true)
      .formatter((cell: ColumnCellTemplateData) => cell.value?.name)
      .build(),
  );
  fields.push(
    selectField()
      .name('currency')
      .label('Moneda')
      .data([
        { value: 'PEN', text: 'PEN - Soles' },
        { value: 'USD', text: 'USD - Dólares' },
      ])
      .defaultValue(() => settings.getCached(Setting.DEFAULT_CURRENCY)?.value ?? 'PEN')
      .visible(false)
      .col(6)
      .build(),
  );
  fields.push(
    currencyField()
      .name('exchangeRate')
      .label('Tipo de cambio')
      .defaultValue(() => {
        const rate = settings.getCached(Setting.DEFAULT_USD_EXCHANGE_RATE)?.value;
        return rate && Number(rate) > 0 ? Number(rate) : 3.8;
      })
      .visible(false)
      .col(6)
      .build(),
  );
  fields.push(
    noneField()
      .name('invoiceFullNumber')
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
  fields.push(textareaField().name('note').label('Nota').visible(false).build());
  fields.push(
    currencyField()
      .col(6)
      .name('discountPercent')
      .label('Descuento %')
      .defaultValue(0)
      .readonly(true)
      .onClick((_e: any) => {
        const total = form?.getFieldValue('grandTotal');
        let discountPercent = form?.getFieldValue('discountPercent');
        discountPercent = discountPercent ? Number(discountPercent / 100) : 0;
        callbacks?.onOpenDiscount?.({ total, discountPercent });
      })
      .onChange((_e: any) => {
        setTotal(form);
      })
      .build(),
  );
  fields.push(
    currencyField()
      .col(6)
      .name('grandTotal')
      .label('Total')
      .formatter((cell: ColumnCellTemplateData) =>
        NumberUtils.formatCurrency(cell.value, cell.data?.currency ?? 'PEN'),
      )
      .disabled(true)
      .build(),
  );
  fields.push(
    noneField()
      .name('status')
      .label('Estado')
      .align('center')
      .formatter((cell: ColumnCellTemplateData) => {
        return SharedUtils.saleStatusFormatter(cell.data);
      })
      .build(),
  );
  fields.push(
    numberField()
      .name('sellerUserId')
      .defaultValue(userInformation().currentUserId)
      .visible(false)
      .hidden(true)
      .build(),
  );

  return fields;
};

export const SaleDetailFields = (form: DxFormEvents | null, currency: string = 'PEN') => {
  const fields: Field[] = [];

  fields.push(identityField().build());
  fields.push(
    entityField(Constants.API_BASE_URL + 'products', 'id', 'name')
      .name('product')
      .label('Producto')
      .minWidth(300)
      .mode('remote')
      .onChange(async function (this: any, row: any, value: any, currentRow: any) {
        const product = (await productService.getOne(value, true)) as ProductDto;
        let price = product.salePrice;
        if (priceList) {
          const priceItem = priceList.find((item) => item.id === value);
          if (priceItem) {
            price = priceItem.price;
          }
        }
        row.product = product;
        row.qty = 1;
        row.unitPrice = price;
        row.netAmount = price;
        this.defaultSetCellValue(row, product, currentRow);
      })
      .formatter((cell: ColumnCellTemplateData) => cell.value?.name)
      .required(true)
      .sortable(false)
      .build(),
  );
  fields.push(
    numberField()
      .name('qty')
      .label('Cantidad')
      .align('center')
      .valueType('number')
      .precision(2)
      .onChange(async function (this: any, row: any, value: any, currentRow: any) {
        if (value === '') return;

        const checkStock = await settings.get(Setting.CHECK_STOCK);

        row.netAmount = currentRow.unitPrice * value;
        if (value > 0 && checkStock?.value == 'true') {
          if (!selectedWarehouseId) {
            toast('Seleccione un almacen para verificar el stock', 'error');
            return;
          }
          const stockData = await GetStockUseCase(currentRow.product.id, selectedWarehouseId);
          toast('Stock actual: ' + stockData.stock, 'info', 2000);
          if (value > stockData.stock) {
            toast('No hay stock suficiente', 'error', 2000);
            value = '';
            row.qty = '';
            row.netAmount = '';
          }
        }
        this.defaultSetCellValue(row, value, currentRow);
      })
      .required(true)
      .sortable(false)
      .build(),
  );
  fields.push(
    currencyField()
      .name('unitPrice')
      .label('Precio')
      .defaultValue(0)
      .onChange(async function (this: any, row: any, value: any, currentRow: any) {
        if (value === '') return;
        row.netAmount = currentRow.qty * value;
        this.defaultSetCellValue(row, value, currentRow);
      })
      .formatter((cell: ColumnCellTemplateData) => NumberUtils.formatCurrency(cell.value, currency))
      .required(true)
      .sortable(false)
      .build(),
  );
  fields.push(
    currencyField()
      .name('netAmount')
      .label('Total')
      .defaultValue(0)
      .onChange(async function (this: any, row: any, value: any, currentRow: any) {
        if (value === '') return;
        this.defaultSetCellValue(row, value, currentRow);
        setTotal(form);
      })
      .formatter((cell: ColumnCellTemplateData) => NumberUtils.formatCurrency(cell.value, currency))
      .required(true)
      .filterable(false)
      .disabled(true)
      .sortable(false)
      .build(),
  );

  return fields;
};

const getSubTotal = (form: DxFormEvents | null) => {
  const rows: { netAmount?: unknown }[] = form?.getDetailData() ?? [];
  let total = 0;
  rows.forEach((row) => {
    total += Number(row.netAmount);
  });
  return total;
};

export const setTotal = (form: DxFormEvents | null) => {
  const subTotal = getSubTotal(form);
  const discountPercent = Number(form?.getFieldValue('discountPercent') ?? 0);
  const discount = subTotal * (discountPercent / 100);
  const total = Number(subTotal - discount).toFixed(2);

  form?.setFieldValue('grandTotal', Number(total));
};
