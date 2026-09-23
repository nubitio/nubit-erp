export type TableStatus = 'libre' | 'ocupada' | 'por_cobrar';

export type OrderItemStatus = 'pendiente' | 'enviado' | 'listo' | 'entregado';

export interface RestaurantAreaMock {
  id: string;
  name: string;
  floor: number;
  orderIndex: number;
}

export interface RestaurantTableMock {
  id: string;
  code: string;
  /** Area display name — kept flat so existing screens showing it as text don't change. */
  area: string;
  areaId: string;
  floor: number;
  areaOrder: number;
  seats: number;
  status: TableStatus;
  currentOrderId: string | null;
  /** Position on the floor-plan editor's canvas; null = not placed yet. */
  positionX: number | null;
  positionY: number | null;
}

export interface OrderItemMock {
  id: string;
  orderId: string;
  productId: string;
  productName: string;
  unitPrice: string;
  qty: number;
  notes?: string;
  status: OrderItemStatus;
  sentAt: string | null;
  readyAt: string | null;
}

export interface OrderMock {
  id: string;
  tableId: string;
  openedAt: string;
  items: OrderItemMock[];
}

export interface MenuItemMock {
  id: string;
  name: string;
  category: string;
  price: number;
}
