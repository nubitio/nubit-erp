import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();
import React, { type MutableRefObject } from 'react';
import type { FormHandle } from '@nubitio/crud';
import { SalePaymentsField } from './components/SalePaymentsField';
import { Field } from '@nubitio/crud';
import {
  identityField,
  entityField,
  currencyField,
  dateField,
  numberField,
  switchField,
  selectField,
  textareaField,
  noneField,
} from '@nubitio/crud';
import toast, { SharedUtils } from '@shared/lib/SharedUtils';
import NumberUtils from '@shared/lib/NumberUtils';
import { httpClient } from '@shared/api/HttpClient';
import { httpRequestData } from '@shared/api/network';
import { activeEntityField, activeOnlyFilter } from '@shared/lib/activeEntityField';
import { productService } from '@features/product';
import { GetStockUseCase } from '@features/pos/lib/GetStockUseCase';
import { mapPosCustomer } from '@features/pos/lib/mapPosCustomer';
import { ProductDto } from '@features/product/types/product.dto';
import { PriceListApplyDto } from '@features/catalog/types/PriceListItemDto';
import { priceListEndpoints } from '@features/catalog/api/priceListEndpoints';
import { Setting, settings } from '@shared/settings';
import type { GridCellContext } from '@nubitio/crud';
import type { DataRecord } from '@nubitio/crud';

type FieldChangeContext = {
  defaultSetCellValue: (row: SaleDetailRow, value: unknown, currentRow: SaleDetailRow) => void;
};

type SaleDetailRow = DataRecord & {
  product?: ProductDto | null;
  qty?: number | string;
  unitPrice?: number;
  discountPercent?: number;
  discountAmount?: number | string;
  netAmount?: number | string;
};

type CustomerSelectEvent = {
  itemData?: unknown;
};

type PriceListChangeEvent = {
  value?: string | number;
};

/**
 * Mutable state shared between SaleFields (header) and SaleDetailFields (lines).
 * Both factory functions must receive the same ref instance so that:
 *   - the warehouse onChange in the header updates `warehouseId` for stock checks in lines
 *   - the price_list onChange in the header updates `priceList` for product price lookup in lines
 *
 * Create with `useRef<SaleFieldStateRef>({ priceList: [], warehouseId: '' })` in the consuming component
 * and pass the same ref to both SaleFields and SaleDetailFields.
 */
export interface SaleFieldStateRef {
  priceList: PriceListApplyDto[];
  warehouseId: string;
}

interface SaleFieldCallbacks {
  branchId?: string | number;
  userId?: string | number;
  onOpenAddCustomer?: () => void;
  /**
   * Called when the user clicks the discount field to open the discount editor.
   * `discountPercent` is in [0, 100] — the same scale used by the form field.
   */
  onOpenDiscount?: (payload: { total: number; discountPercent: number }) => void | Promise<void>;
  /** Shared mutable state between header and detail field factories. See SaleFieldStateRef. */
  stateRef?: MutableRefObject<SaleFieldStateRef>;
}

interface SaleDetailFieldCallbacks {
  onOpenItemDiscount?: (payload: {
    rowIndex: number;
    baseValue: number;
    /** Discount percentage in [0, 100]. */
    discountPercent: number;
    discountAmount: number;
  }) => void;
  /** Shared mutable state between header and detail field factories. See SaleFieldStateRef. */
  stateRef?: MutableRefObject<SaleFieldStateRef>;
}

const applyPriceListToSaleDetail = async (
  form: FormHandle | null,
  priceListId: string | number,
  stateRef?: MutableRefObject<SaleFieldStateRef>,
) => {
  const fetchedPriceList = await httpRequestData<PriceListApplyDto[]>(
    httpClient.get<PriceListApplyDto[]>(priceListEndpoints.apply(priceListId)),
  );

  if (stateRef) {
    stateRef.current.priceList = fetchedPriceList;
  }

  const detailData = form?.getDetailData() ?? [];
  let changed = false;
  detailData.forEach((item: SaleDetailRow) => {
    const actualPrice = item.unitPrice;
    const priceItem = fetchedPriceList.find((price) => price.id === item.product?.id);
    const newPrice = Number(priceItem?.price ?? 0);
    if (priceItem && newPrice !== actualPrice) {
      item.unitPrice = newPrice;
      item.netAmount = Number(item.qty ?? 0) * newPrice;
      changed = true;
    }
  });

  if (changed) {
    form?.reloadDetailData();
    setTotal(form);
  }
};

/** Restore base product prices on detail rows (customer without price list). */
const restoreBasePricesInSaleDetail = (form: FormHandle | null) => {
  const detailData = form?.getDetailData() ?? [];
  let changed = false;
  detailData.forEach((item: SaleDetailRow) => {
    const basePrice = Number(item.product?.salePrice ?? Number.NaN);
    if (Number.isFinite(basePrice) && basePrice !== item.unitPrice) {
      item.unitPrice = basePrice;
      item.netAmount = Number(item.qty ?? 0) * basePrice;
      changed = true;
    }
  });

  if (changed) {
    form?.reloadDetailData();
    setTotal(form);
  }
};

const isStockTrackedProduct = (product: ProductDto | DataRecord | null | undefined) => {
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
    switchField()
      .name('emitInvoice')
      .label('Emitir comprobante')
      .valueType('boolean')
      .defaultValue(() => settings.getCached(Setting.AUTO_INVOICE)?.value === 'true')
      .visibleOnForm(true)
      .visible(false)
      .build(),
  );
  fields.push(
    switchField()
      .name('creditSale')
      .label('Venta a crédito')
      .valueType('boolean')
      .defaultValue(false)
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
      .label('Almacén')
      .filters([
        {
          field: 'branch',
          operator: '=',
          value: callbacks?.branchId ?? '',
        },
        ...activeOnlyFilter(),
      ])
      .autoSelectIfSingle(true)
      .onChange((e: unknown) => {
        // Write warehouse selection into shared state so detail fields can use it for stock checks.
        if (callbacks?.stateRef) {
          callbacks.stateRef.current.warehouseId = String(e ?? '');
        }
      })
      .visible(false)
      .required(true)
      .build(),
  );
  fields.push(
    activeEntityField(API + 'customers', 'id', 'businessName')
      .name('customer')
      .label('Cliente')
      .width(240)
      .defaultValue(1)
      .required(true)
      .formatter((cell: GridCellContext) => (cell.value as { businessName?: string } | undefined)?.businessName)
      .onSelect((e: unknown) => {
        const event = e as CustomerSelectEvent;
        // Same extraction as POS (mapPosCustomer): the API serializes the
        // customer's price list as an IRI string under `priceList`.
        const priceListId = mapPosCustomer(event.itemData)?.priceListId;
        if (priceListId != null) {
          form?.setFieldValue('price_list', priceListId);
          void applyPriceListToSaleDetail(form, priceListId, callbacks?.stateRef);
          return;
        }

        form?.setFieldValue('price_list', null);
        if (callbacks?.stateRef) {
          callbacks.stateRef.current.priceList = [];
        }
        restoreBasePricesInSaleDetail(form);
      })
      .buttons([
        {
          name: 'add',
          location: 'first',
          options: {
            icon: 'add',
            stylingMode: 'text',
            onClick: () => {
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
      .onChange(async (event: PriceListChangeEvent) => {
        if (event.value == null || event.value === '') {
          if (callbacks?.stateRef) {
            callbacks.stateRef.current.priceList = [];
          }
          return;
        }

        await applyPriceListToSaleDetail(form, event.value, callbacks?.stateRef);
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
      .visibleOnForm(false)
      .formatter((cell: GridCellContext) => {
        const payments = (cell.data.payments ?? []) as {
          method_name?: string | null;
          amount?: string;
        }[];
        if (payments.length > 1) {
          const currency = (cell.data.currency as string) ?? 'PEN';
          const label = payments
            .map((payment) => payment.method_name)
            .filter(Boolean)
            .join(' + ');
          const detail = payments
            .map(
              (payment) =>
                `${payment.method_name ?? '—'} ${NumberUtils.formatCurrency(Number(payment.amount ?? 0), currency)}`,
            )
            .join(' · ');
          return <span title={detail}>{label}</span>;
        }
        return (cell.value as { name?: string } | undefined)?.name;
      })
      .build(),
  );
  fields.push(
    noneField()
      .name('payments')
      .label('Pagos')
      .visible(false)
      .visibleOnForm(false)
      .defaultValue(() => [])
      .build(),
  );
  fields.push(
    noneField()
      .name('salePayments')
      .label('')
      .forceFullWidth(true)
      .contentRender(() => {
        const total = Number(form?.getFieldValue('grandTotal') ?? 0);
        const currency = String(form?.getFieldValue('currency') ?? 'PEN');
        return <SalePaymentsField form={form} total={total} currency={currency} />;
      })
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
      .onChange((value: string | { value?: string } | null | undefined) => {
        const currency = typeof value === 'string' ? value : (value?.value ?? value);
        if (currency && currency !== 'PEN') {
          form?.showField('exchangeRate');
        } else {
          form?.hideField('exchangeRate');
        }
      })
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
      .helpText('Clic para aplicar descuento')
      .onClick(() => {
        const total = getSubTotal(form);
        const rawDiscountPercent = form?.getFieldValue('discountPercent');
        // discountPercent is stored as 0–100 on the form — pass it directly (no conversion).
        const discountPercent = rawDiscountPercent ? Number(rawDiscountPercent) : 0;
        callbacks?.onOpenDiscount?.({ total, discountPercent });
      })
      .onChange(() => {
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
      .visibleOnForm(false)
      .disabled(true)
      .build(),
  );
  fields.push(
    dateField()
      .name('dueDate')
      .label('Vencimiento')
      .visibleOnForm(false)
      .formatter((cell: GridCellContext) => SharedUtils.dateFormatter(cell.value as string | Date | null | undefined))
      .valueType('date')
      .build(),
  );
  fields.push(
    currencyField()
      .name('balanceDue')
      .label('Saldo pendiente')
      .visibleOnForm(false)
      .disabled(true)
      .formatter((cell: GridCellContext) =>
        NumberUtils.formatCurrency(cell.value as number, cell.data.currency as string ?? 'PEN'),
      )
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
      .onChange(async function (this: FieldChangeContext, rowData: DataRecord, value: unknown, currentRowData: DataRecord) {
        const row = rowData as SaleDetailRow;
        const currentRow = currentRowData as SaleDetailRow;
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

        const product = (await productService.getOne(String(value), true)) as ProductDto;
        // Read price list from shared state (set when user selects a tarifa in the header).
        const currentPriceList = callbacks?.stateRef?.current.priceList ?? [];
        let price = product.salePrice;
        if (currentPriceList.length > 0) {
          const priceItem = currentPriceList.find((item) => item.id === value);
          if (priceItem) {
            price = Number(priceItem.price);
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
      .formatter((cell: GridCellContext) => {
        const row = cell.data as { nameSnapshot?: string } | undefined;
        const product = cell.value as { name?: string } | undefined;
        return row?.nameSnapshot || product?.name;
      })
      .required(true)
      .sortable(false)
      .build(),
  );
  fields.push(
    numberField()
      .name('qty')
      .label('Cantidad')
      .width(92)
      .align('center')
      .valueType('number')
      .precision(2)
      .onChange(async function (this: FieldChangeContext, rowData: DataRecord, value: unknown, currentRowData: DataRecord) {
        const row = rowData as SaleDetailRow;
        const currentRow = currentRowData as SaleDetailRow;
        if (value === '') return;
        const qty = Number(value);

        const stockPolicy = await settings.get(Setting.INVENTORY_NEGATIVE_STOCK_POLICY);

        const discountPercent = Number(currentRow.discountPercent ?? 0);
        const grossAmount = Number(currentRow.unitPrice ?? 0) * qty;
        const discountAmount = Number((grossAmount * (discountPercent / 100)).toFixed(2));
        row.discountAmount = discountAmount;
        row.netAmount = Number((grossAmount - discountAmount).toFixed(2));

        // Read warehouse from shared state (set when user selects an almacén in the header).
        const currentWarehouseId = callbacks?.stateRef?.current.warehouseId ?? '';

        const product = currentRow.product;
        if (qty > 0 && stockPolicy?.value !== 'ALLOW_NEGATIVE' && product && isStockTrackedProduct(product)) {
          if (!currentWarehouseId) {
            toast('Seleccione un almacen para verificar el stock', 'error');
            return;
          }
          const stockData = await GetStockUseCase(product.id, currentWarehouseId);
          toast('Stock actual: ' + stockData.stock, 'info', 2000);
          if (qty > stockData.stock) {
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
      .width(110)
      .align('right')
      .defaultValue(0)
      .onChange(async function (this: FieldChangeContext, rowData: DataRecord, value: unknown, currentRowData: DataRecord) {
        const row = rowData as SaleDetailRow;
        const currentRow = currentRowData as SaleDetailRow;
        if (value === '') return;
        const discountPercent = Number(currentRow.discountPercent ?? 0);
        const grossAmount = Number(currentRow.qty ?? 0) * Number(value);
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
      .label('Dto. %')
      .width(75)
      .align('right')
      .valueType('number')
      .precision(2)
      .defaultValue(0)
      .onChange(async function (this: FieldChangeContext, rowData: DataRecord, value: unknown, currentRowData: DataRecord) {
        const row = rowData as SaleDetailRow;
        const currentRow = currentRowData as SaleDetailRow;
        if (value === '') return;
        const percent = Math.min(100, Math.max(0, Number(value)));
        const grossAmount = Number(currentRow.qty ?? 0) * Number(currentRow.unitPrice ?? 0);
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
      .label('Dto. S/')
      .defaultValue(0)
      .formatter((cell: GridCellContext) => NumberUtils.formatCurrency((cell.value as number | undefined) ?? 0, currency))
      .filterable(false)
      .disabled(true)
      .sortable(false)
      .visible(false)   // hidden — redundant alongside Dto. % and Total columns
      .build(),
  );
  fields.push(
    currencyField()
      .name('netAmount')
      .label('Total')
      .width(120)
      .align('right')
      .defaultValue(0)
      .onChange(async function (this: FieldChangeContext, rowData: DataRecord, value: unknown, currentRowData: DataRecord) {
        const row = rowData as SaleDetailRow;
        const currentRow = currentRowData as SaleDetailRow;
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
