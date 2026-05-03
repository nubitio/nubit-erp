export type LocalSaleStatus = 'pending' | 'syncing' | 'synced' | 'error';
export type OutboxStatus = 'pending' | 'retrying' | 'done' | 'error';

export interface LocalSaleTotals {
  subtotal: number;
  igv: number;
  discount: number;
  total: number;
}

export interface LocalSaleItem {
  productId: string;
  name: string;
  quantity: number;
  unitPrice: number;
  lineTotal: number;
}

export interface LocalSalePayment {
  methodId: number;
  methodName?: string;
  amount: number;
  amountReceived?: number;
  changeAmount?: number;
}

export interface LocalSale {
  id: string;
  tenantId: string;
  storeId: string;
  warehouseId: string;
  correlativoTemporal: string;
  items: LocalSaleItem[];
  payments: LocalSalePayment[];
  totals: LocalSaleTotals;
  status: LocalSaleStatus;
  createdAt: string;
  updatedAt: string;
  idempotencyKey: string;
  serverSaleId?: string;
  serverCode?: string;
  errorMessage?: string;
  errorCode?: string;
  errorAction?: string;
  numericErrorCode?: number;
}

export interface OutboxRequest {
  method: 'POST' | 'PUT' | 'PATCH' | 'DELETE';
  url: string;
  body?: unknown;
  headers?: Record<string, string>;
}

export interface OutboxItem {
  id: string;
  request: OutboxRequest;
  saleId: string;
  status: OutboxStatus;
  attempts: number;
  lastError?: string;
  lastErrorCode?: string;
  lastErrorAction?: string;
  lastNumericErrorCode?: number;
  createdAt: string;
  nextRetryAt?: string;
}

export interface SyncLog {
  id?: number;
  eventType: 'sale_created_offline' | 'sale_sync_success' | 'sale_sync_error' | 'outbox_retry';
  saleId?: string;
  detail?: string;
  createdAt: string;
}

export interface ProductCache {
  id: string;
  raw: unknown;
  name: string;
  sku?: string;
  barcode?: string;
  updatedAt: string;
  lastSyncedAt: string;
}

export interface CustomerCache {
  id: string;
  raw: unknown;
  businessName: string;
  doc?: string;
  updatedAt: string;
  lastSyncedAt: string;
}

export interface CreateOfflineSaleInput {
  tenantId: string;
  storeId: string;
  warehouseId: string;
  items: LocalSaleItem[];
  payments: LocalSalePayment[];
  totals: LocalSaleTotals;
  requestBody: unknown;
  endpointUrl: string;
}
