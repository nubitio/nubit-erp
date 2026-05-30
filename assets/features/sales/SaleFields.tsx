import { getCoreApiBaseUrl } from '@nubit/core';

const API = getCoreApiBaseUrl();
import type { FormHandle } from '@nubit/crud';
import { Field } from '@nubit/crud';
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
} from '@nubit/crud';
import toast, { SharedUtils } from '@shared/lib/SharedUtils';
import NumberUtils from '@shared/lib/NumberUtils';
import { httpClient } from '@shared/api/HttpClient';
import { httpRequestData } from '@shared/api/network';
import { productService } from '@features/product';
import { GetStockUseCase } from '@features/pos/lib/GetStockUseCase';
import { ProductDto } from '@features/product/types/product.dto';
import { PriceListItemDto } from '@features/catalog/types/PriceListItemDto';
import { Setting, settings } from '@app/Settings';
import type { GridCellContext } from '@nubit/crud';
import type { DataRecord } from '@nubit/crud';

interface SaleFieldCallbacks {
  branchId?: string | number;
  userId?: string | number;
  onOpenAddCustomer?: () => void;
  onOpenDiscount?: (payload: { total: number; discountPercent: number }) => void | Promise<void>;
}

interface SaleDetailFieldCallbacks {
  onOpenItemDiscount?: (payload: {
    rowIndex: number;
    baseValue: number;
    discountPercent: number;
    discountAmount: number;
  }) => void;
}

let priceList: PriceListItemDto[] = [];
let selectedWarehouseId = '';

const isStockTrackedProduct = (product: ProductDto | DataRecord | undefined) => {
  if (!product) return false;

  const inventoryBehavior = String(product.inventoryBehavior ?? '').toUpperCase();
  if (inventoryBehavior) return inventoryBehavior === 'TRACKED';

  if (typeof product.trackInventory === 'boolean') return product.trackInventory;

  const productKind = String(product.productKind ?? '').toUpperCase();
  const productType = String(product.productType ?? '').toUpperCase();
  return productKind === 'GOOD' || productType === 'STOCKABLE' || product.type === 1;
};

export const SaleFields = (form: FormHandle | null, callbacks?: SaleFieldCallbacks) => {
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
      .name('issuedDate')
      .label('Fecha')
      .required(true)
      .formatter((cell: GridCellContext) => SharedUtils.dateFormatter(cell.value as string | Date | null | undefined))
      .valueType('date')
      .build(),
  );
  fields.push(
    entityField(API + 'warehouses', 'id', 'name')
      .name('warehouse')
      .label('Almacen')
      .filters([
        {
          field: 'branch',
          operator: '=',
          value: callbacks?.branchId ?? '',
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
    entityField(API + 'customers', 'id', 'businessName')
      .name('customer')
      .label('Cliente')
      .width(240)
      .defaultValue(1)
      .required(true)
      .formatter((cell: GridCellContext) => (cell.value as { businessName?: string } | undefined)?.businessName)
      .onSelect((event: any) => {
        if (event.itemData?.price_list) {
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
    entityField(API + 'price-lists', 'id', 'name')
      .name('price_list')
      .label('Tarifa')
      .valueType('number')
      .visible(false)
      .onChange(async (event: any) => {
        priceList = await httpRequestData<PriceListItemDto[]>(
          httpClient.get<PriceListItemDto[]>(`price-list/${event.value}/get`),
        );
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
    entityField(API + 'payment-methods', 'id', 'name')
      .name('paymentMethod')
      .label('Método de pago')
      .valueType('number')
      .defaultValue(1)
      .required(true)
      .formatter((cell: GridCellContext) => (cell.value as { name?: string } | undefined)?.name)
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
      .visibleOnForm(false)
      .formatter((cell: GridCellContext) => {
        if (cell.value) {
          const url = `/receipt/${cell.data.id}/ticket?format=a4`;
          return (
            <a href={url} className="text-reset" target="_blank" rel="noreferrer">
              <i className="ph ph-file-pdf text-emerald-700" /> {cell.value as string}
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
      .helpText('Click para aplicar descuento')
      .onClick((_e: any) => {
        const total = getSubTotal(form);
        const rawDiscountPercent = form?.getFieldValue('discountPercent');
        const discountPercent = rawDiscountPercent ? Number(rawDiscountPercent) / 100 : 0;
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
      .formatter((cell: GridCellContext) =>
        NumberUtils.formatCurrency(cell.value as number, cell.data.currency as string ?? 'PEN'),
      )
      .disabled(true)
      .build(),
  );
  fields.push(
    noneField()
      .name('status')
      .label('Estado')
      .visibleOnForm(false)
      .align('center')
      .formatter((cell: GridCellContext) => {
        return SharedUtils.saleStatusFormatter(cell.data);
      })
      .build(),
  );
  fields.push(
    numberField()
      .name('sellerUserId')
      .defaultValue(callbacks?.userId)
      .visible(false)
      .hidden(true)
      .build(),
  );

  return fields;
};

export const SaleDetailFields = (
  form: FormHandle | null,
  currency: string = 'PEN',
  callbacks?: SaleDetailFieldCallbacks,
) => {
  const fields: Field[] = [];

  fields.push(identityField().build());
  fields.push(
    entityField(API + 'products', 'id', 'name')
      .name('product')
      .label('Producto')
      .minWidth(300)
      .onChange(async function (this: any, row: any, value: any, currentRow: any) {
        if (value === '' || value == null) {
          row.product = null;
          row.qty = '';
          row.unitPrice = 0;
          row.discountPercent = 0;
          row.discountAmount = 0;
          row.netAmount = 0;
          this.defaultSetCellValue(row, null, currentRow);
          return;
        }

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
        row.discountPercent = 0;
        row.discountAmount = 0;
        row.netAmount = price;
        this.defaultSetCellValue(row, product, currentRow);
      })
      .formatter((cell: GridCellContext) => (cell.value as { name?: string } | undefined)?.name)
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

        const stockPolicy = await settings.get(Setting.INVENTORY_NEGATIVE_STOCK_POLICY);

        const discountPercent = Number(currentRow.discountPercent ?? 0);
        const grossAmount = currentRow.unitPrice * value;
        const discountAmount = Number((grossAmount * (discountPercent / 100)).toFixed(2));
        row.discountAmount = discountAmount;
        row.netAmount = Number((grossAmount - discountAmount).toFixed(2));

        if (value > 0 && stockPolicy?.value !== 'ALLOW_NEGATIVE' && isStockTrackedProduct(currentRow.product)) {
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
            row.discountAmount = '';
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
        const discountPercent = Number(currentRow.discountPercent ?? 0);
        const grossAmount = currentRow.qty * value;
        const discountAmount = Number((grossAmount * (discountPercent / 100)).toFixed(2));
        row.discountAmount = discountAmount;
        row.netAmount = Number((grossAmount - discountAmount).toFixed(2));
        this.defaultSetCellValue(row, value, currentRow);
      })
      .formatter((cell: GridCellContext) => NumberUtils.formatCurrency(cell.value as number, currency))
      .required(true)
      .sortable(false)
      .build(),
  );
  fields.push(
    numberField()
      .name('discountPercent')
      .label('Desc. %')
      .align('right')
      .valueType('number')
      .precision(2)
      .defaultValue(0)
      .onChange(async function (this: any, row: any, value: any, currentRow: any) {
        if (value === '') return;
        const percent = Math.min(100, Math.max(0, Number(value)));
        const grossAmount = currentRow.qty * currentRow.unitPrice;
        const discountAmount = Number((grossAmount * (percent / 100)).toFixed(2));
        row.discountAmount = discountAmount;
        row.netAmount = Number((grossAmount - discountAmount).toFixed(2));
        this.defaultSetCellValue(row, percent, currentRow);
      })
      .filterable(false)
      .sortable(false)
      .build(),
  );
  fields.push(
    currencyField()
      .name('discountAmount')
      .label('Desc. S/')
      .defaultValue(0)
      .formatter((cell: GridCellContext) => NumberUtils.formatCurrency((cell.value as number | undefined) ?? 0, currency))
      .filterable(false)
      .disabled(true)
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
      .formatter((cell: GridCellContext) => NumberUtils.formatCurrency(cell.value as number, currency))
      .required(true)
      .filterable(false)
      .disabled(true)
      .sortable(false)
      .build(),
  );

  // Discount icon button — opens the global DiscountDialog scoped to this item row.
  if (callbacks?.onOpenItemDiscount) {
    const openItemDiscount = callbacks.onOpenItemDiscount;
    fields.push(
      noneField()
        .name('_itemDiscountAction')
        .label('')
        .width(40)
        .align('center')
        .filterable(false)
        .sortable(false)
        .formatter((cell: GridCellContext) => {
          const row = cell.data as {
            qty?: number;
            unitPrice?: number;
            discountPercent?: number;
            discountAmount?: number;
          };
          const qty = Number(row.qty ?? 0);
          const unitPrice = Number(row.unitPrice ?? 0);
          const baseValue = qty * unitPrice;
          const discountPercent = Number(row.discountPercent ?? 0);
          const discountAmount = Number(row.discountAmount ?? 0);
          const rowIndex: number = cell.rowIndex ?? 0;
          return (
            <button
              type="button"
              title="Descuento por ítem"
              style={{
                background: 'none',
                border: 'none',
                cursor: 'pointer',
                padding: '2px 4px',
                color: discountPercent > 0 ? '#0d6efd' : '#6c757d',
              }}
              onClick={() =>
                openItemDiscount({ rowIndex, baseValue, discountPercent, discountAmount })
              }
            >
              <i className="ph ph-tag-chevron" style={{ fontSize: '1.1rem' }} />
            </button>
          );
        })
        .build(),
    );
  }

  return fields;
};

const getSubTotal = (form: FormHandle | null) => {
  const rows: { netAmount?: unknown }[] = form?.getDetailData() ?? [];
  let total = 0;
  rows.forEach((row) => {
    total += Number(row.netAmount);
  });
  return total;
};

export const setTotal = (form: FormHandle | null) => {
  const subTotal = getSubTotal(form);
  const discountPercent = Number(form?.getFieldValue('discountPercent') ?? 0);
  const discount = subTotal * (discountPercent / 100);
  const total = Number(subTotal - discount).toFixed(2);

  form?.setFieldValue('grandTotal', Number(total));
};
