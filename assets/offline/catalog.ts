import { offlineDb } from './db';
import type { CustomerCache, ProductCache } from './types';

type ProductPayload = Record<string, unknown>;
type CustomerPayload = Record<string, unknown>;

export const persistProducts = async (products: ProductPayload[]) => {
  const now = new Date().toISOString();
  const rows: ProductCache[] = products.map((product) => ({
    id: String(product?.id ?? ''),
    name: String(product?.name ?? 'Producto'),
    sku: product.sku ? String(product.sku) : undefined,
    barcode: product.barcode ? String(product.barcode) : undefined,
    raw: product,
    updatedAt: now,
    lastSyncedAt: now,
  }));

  await offlineDb.transaction('rw', offlineDb.products, async () => {
    await offlineDb.products.clear();
    await offlineDb.products.bulkPut(rows);
  });

  return rows.length;
};

export const getCachedProducts = async () => {
  return offlineDb.products.toArray();
};

export const getLastProductsSyncAt = async () => {
  const row = await offlineDb.products.orderBy('lastSyncedAt').last();
  return row?.lastSyncedAt ?? null;
};

export const updateCachedProductPrice = async (productId: string, salePrice: string) => {
  const row = await offlineDb.products.get(productId);
  if (!row || !row.raw || typeof row.raw !== 'object') {
    return;
  }

  const now = new Date().toISOString();
  await offlineDb.products.put({
    ...row,
    raw: {
      ...(row.raw as Record<string, unknown>),
      salePrice,
      precio_unitario: salePrice,
      price: salePrice,
    },
    updatedAt: now,
  });
};

export const persistCustomers = async (customers: CustomerPayload[]) => {
  const now = new Date().toISOString();
  const rows: CustomerCache[] = customers.map((customer) => ({
    id: String(customer?.id ?? ''),
    businessName: String(customer.businessName ?? customer.name ?? 'Cliente'),
    doc: customer.ruc ? String(customer.ruc) : undefined,
    raw: customer,
    updatedAt: now,
    lastSyncedAt: now,
  }));

  await offlineDb.transaction('rw', offlineDb.customers, async () => {
    await offlineDb.customers.clear();
    await offlineDb.customers.bulkPut(rows);
  });
};
