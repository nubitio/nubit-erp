export interface PosItem {
  id: string;
  name: string;
  salePrice: number;
  photo?: Image;
  quantity?: number;
  category: Category;
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
