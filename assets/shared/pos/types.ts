import type { SunatTaxType } from '@shared/domain/sale.types';

export type { SunatTaxType };

export interface PosItem {
  id: string;
  name: string;
  /** Canonical decimal amount from the API; never store currency as a JS float. */
  salePrice: string;
  productType?: 'STOCKABLE' | 'SERVICE' | 'DIGITAL' | 'COMPOSITE' | 'CONSUMABLE';
  trackInventory?: boolean;
  saleUnitCode?: string;
  sku?: string;
  barcode?: string;
  stock?: number;
  description?: string;
  photo?: Image;
  quantity?: number;
  category: Category;
  taxType: SunatTaxType;
}

export type CartItem = PosItem & {
  quantity: number;
  lineDescription?: string;
};

export interface CustomerOption {
  id: number;
  businessName: string;
  doc?: string;
  /** ID of the price list assigned to this customer, if any. */
  priceListId?: number;
}

export interface PaymentMethodOption {
  id: number;
  name: string;
  isActive: boolean;
}

export interface WarehouseOption {
  id: string;
  name: string;
  branch?: { id?: string | number };
}

export interface Category {
  id: string;
  name: string;
  icon?: string;
  active?: boolean;
}

export interface Image {
  id: string;
  path: string;
}
