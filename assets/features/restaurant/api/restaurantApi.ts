import { httpClient } from '@shared/api/HttpClient';
import { extractCollection } from '@shared/pos/helpers';
import { getCoreApiBaseUrl } from '@nubitio/core';
import type {
  MenuItemMock,
  OrderItemMock,
  OrderItemStatus,
  OrderMock,
  RestaurantAreaMock,
  RestaurantTableMock,
  TableStatus,
} from '../types';

const API = getCoreApiBaseUrl();

// The backend (`src/Restaurant`) speaks plain English enum values; the UI was
// designed and validated against the prototype's Spanish-ish vocabulary
// (`libre`/`ocupada`/`por_cobrar`, `pendiente`/`enviado`/`listo`/`entregado`).
// Translating at the fetch boundary means none of the already-approved
// screens/CSS need to change.
const TABLE_STATUS_FROM_API: Record<string, TableStatus> = {
  FREE: 'libre',
  OCCUPIED: 'ocupada',
  AWAITING_PAYMENT: 'por_cobrar',
};

const ITEM_STATUS_FROM_API: Record<string, OrderItemStatus> = {
  PENDING: 'pendiente',
  SENT: 'enviado',
  READY: 'listo',
  DELIVERED: 'entregado',
};

interface ApiArea {
  id: number;
  name: string;
  floor: number;
  orderIndex: number;
}

interface ApiTable {
  id: number;
  code: string;
  area: ApiArea;
  seats: number;
  status: string;
  positionX: number | null;
  positionY: number | null;
}

interface ApiOrderItem {
  id: number;
  productId: string;
  productName: string;
  unitPrice: string;
  qty: number;
  notes: string | null;
  status: string;
  sentAt: string | null;
  readyAt: string | null;
  deliveredAt: string | null;
}

interface ApiOrder {
  id: number;
  tableId: number;
  openedAt: string;
  closedAt: string | null;
  saleId: string | null;
  items: ApiOrderItem[];
}

function mapTable(raw: ApiTable): RestaurantTableMock {
  return {
    id: String(raw.id),
    code: raw.code,
    area: raw.area.name,
    areaId: String(raw.area.id),
    floor: raw.area.floor,
    areaOrder: raw.area.orderIndex,
    seats: raw.seats,
    status: TABLE_STATUS_FROM_API[raw.status] ?? 'libre',
    currentOrderId: null,
    positionX: raw.positionX ?? null,
    positionY: raw.positionY ?? null,
  };
}

function mapArea(raw: ApiArea): RestaurantAreaMock {
  return {
    id: String(raw.id),
    name: raw.name,
    floor: raw.floor,
    orderIndex: raw.orderIndex,
  };
}

function mapOrderItem(raw: ApiOrderItem, orderId: string): OrderItemMock {
  return {
    id: String(raw.id),
    orderId,
    productId: raw.productId,
    productName: raw.productName,
    unitPrice: raw.unitPrice,
    qty: raw.qty,
    notes: raw.notes ?? undefined,
    status: ITEM_STATUS_FROM_API[raw.status] ?? 'pendiente',
    sentAt: raw.sentAt,
    readyAt: raw.readyAt,
  };
}

function mapOrder(raw: ApiOrder): OrderMock {
  const id = String(raw.id);
  return {
    id,
    tableId: String(raw.tableId),
    openedAt: raw.openedAt,
    items: raw.items.map((item) => mapOrderItem(item, id)),
  };
}

export async function fetchTables(): Promise<RestaurantTableMock[]> {
  const response = await httpClient.get<unknown>(`${API}restaurant/tables?pagination=false`);
  return extractCollection<ApiTable>(response.data).map(mapTable);
}

export async function fetchAreas(): Promise<RestaurantAreaMock[]> {
  const response = await httpClient.get<unknown>(`${API}restaurant/areas?pagination=false`);
  return extractCollection<ApiArea>(response.data).map(mapArea);
}

/** Every order, open or closed — the caller filters by `closedAt` when it needs "open only". */
export async function fetchOrders(): Promise<{
  orders: OrderMock[];
  openOrderIdByTableId: Map<string, string>;
}> {
  const response = await httpClient.get<unknown>(`${API}restaurant/orders?pagination=false`);
  const raw = extractCollection<ApiOrder>(response.data);
  // The API omits null properties entirely rather than sending `null`, so an
  // open order's `closedAt` key is simply absent (`undefined`), not `null`.
  const orders = raw.filter((o) => o.closedAt == null).map(mapOrder);
  const openOrderIdByTableId = new Map(orders.map((o) => [o.tableId, o.id]));
  return { orders, openOrderIdByTableId };
}

export async function openTable(tableId: string): Promise<OrderMock> {
  const response = await httpClient.post<ApiOrder>(`${API}restaurant/orders`, {
    tableId: Number(tableId),
  });
  return mapOrder(response.data);
}

export async function addOrderItem(
  orderId: string,
  productId: string,
  qty: number,
  notes?: string,
): Promise<OrderItemMock> {
  const response = await httpClient.post<ApiOrderItem>(`${API}restaurant/order-items`, {
    orderId: Number(orderId),
    productId,
    qty,
    notes: notes ?? null,
  });
  return mapOrderItem(response.data, orderId);
}

export async function sendToKitchen(orderId: string): Promise<void> {
  await httpClient.post(`${API}restaurant/orders/${orderId}/send-to-kitchen`, {});
}

export async function requestBill(orderId: string): Promise<void> {
  await httpClient.post(`${API}restaurant/orders/${orderId}/request-bill`, {});
}

export async function closeOrder(orderId: string, saleId: string): Promise<void> {
  await httpClient.post(`${API}restaurant/orders/${orderId}/close`, { saleId });
}

export async function markItemReady(itemId: string): Promise<void> {
  await httpClient.post(`${API}restaurant/order-items/${itemId}/ready`, {});
}

export async function markItemDelivered(itemId: string): Promise<void> {
  await httpClient.post(`${API}restaurant/order-items/${itemId}/delivered`, {});
}

export async function updateTablePosition(
  tableId: string,
  positionX: number,
  positionY: number,
): Promise<void> {
  await httpClient.patch(`${API}restaurant/tables/${tableId}`, { positionX, positionY });
}

// ─── Menu (real catalog, replaces the prototype's hardcoded MOCK_MENU) ────────

interface ApiProduct {
  id: string;
  name: string;
  salePrice: string;
  category?: { id: string; name: string } | null;
}

export async function fetchMenu(): Promise<MenuItemMock[]> {
  const response = await httpClient.get<unknown>(`${API}products?pagination=false`);
  return extractCollection<ApiProduct>(response.data).map((product) => ({
    id: product.id,
    name: product.name,
    category: product.category?.name ?? 'Otros',
    price: Number(product.salePrice),
  }));
}
