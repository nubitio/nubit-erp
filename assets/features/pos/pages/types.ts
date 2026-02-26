export interface PosItem {
  id: string;
  name: string;
  salePrice: number;
  sku?: string;
  barcode?: string;
  stock?: number;
  description?: string;
  photo?: Image;
  quantity?: number;
  category: Category;
}

export type CartItem = PosItem & {
  quantity: number;
};

export interface CustomerOption {
  id: number;
  businessName: string;
  doc?: string;
}

export interface PaymentMethodOption {
  id: number;
  name: string;
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
