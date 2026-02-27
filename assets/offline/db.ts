import Dexie, { type Table } from 'dexie';
import type { CustomerCache, LocalSale, OutboxItem, ProductCache, SyncLog } from './types';

class EfactOfflineDb extends Dexie {
  products!: Table<ProductCache, string>;
  customers!: Table<CustomerCache, string>;
  sales!: Table<LocalSale, string>;
  outbox!: Table<OutboxItem, string>;
  sync_logs!: Table<SyncLog, number>;

  constructor() {
    super('efact_offline_db');
    this.version(1).stores({
      products: 'id, name, sku, barcode, updatedAt, lastSyncedAt',
      customers: 'id, businessName, doc, updatedAt, lastSyncedAt',
      sales: 'id, status, createdAt, updatedAt, tenantId, storeId, warehouseId',
      outbox: 'id, saleId, status, createdAt, nextRetryAt, attempts',
      sync_logs: '++id, eventType, saleId, createdAt',
    });
  }
}

export const offlineDb = new EfactOfflineDb();
