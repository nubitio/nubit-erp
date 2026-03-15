import { DateTime } from 'luxon';
import { LIMA_TZ } from '@shared/lib/DateUtils';
import { offlineDb } from './db';
import { nextRetryDelayMs } from './backoff';
import { logSyncEvent } from './telemetry';
import { sendApiRequest } from './api';
import type {
  CreateOfflineSaleInput,
  LocalSale,
  LocalSaleStatus,
  OutboxItem,
  OutboxStatus,
} from './types';

/** Maximum number of sync attempts before an outbox item is permanently failed. */
const MAX_SYNC_ATTEMPTS = 10;

const OFFLINE_CORRELATIVE_PREFIX = 'OFF';
let activeSyncPromise: Promise<void> | null = null;

const asIso = (date: Date) => date.toISOString();

const buildTemporalCorrelative = async () => {
  // Use Lima time so the daily counter rolls over at Lima midnight (00:00 Lima = 05:00 UTC),
  // not at UTC midnight (19:00 Lima). Without this, sales made between 19:00–23:59 Lima time
  // would be counted against the next calendar day, producing duplicate correlatives.
  const now = DateTime.now().setZone(LIMA_TZ);
  const year = now.year;
  const dayStart = now.startOf('day'); // 00:00:00 Lima
  const dayEnd = now.endOf('day'); // 23:59:59.999 Lima
  const count = await offlineDb.sales
    .where('createdAt')
    .between(dayStart.toUTC().toISO()!, dayEnd.toUTC().toISO()!, true, true)
    .count();
  return `${OFFLINE_CORRELATIVE_PREFIX}-${year}-${String(count + 1).padStart(6, '0')}`;
};

const buildServerSaleCode = (created: any, fallback: string) => {
  const composed = [created?.serie, created?.numero].filter(Boolean).join('-');
  return composed || created?.invoice || created?.id || fallback;
};

export const createLocalSaleAndEnqueue = async (input: CreateOfflineSaleInput) => {
  const now = new Date();
  const nowIso = asIso(now);
  const saleId = crypto.randomUUID();
  const idempotencyKey = crypto.randomUUID();
  const correlativoTemporal = await buildTemporalCorrelative();
  const outboxId = crypto.randomUUID();

  const sale: LocalSale = {
    id: saleId,
    tenantId: input.tenantId,
    storeId: input.storeId,
    warehouseId: input.warehouseId,
    correlativoTemporal,
    items: input.items,
    payments: input.payments,
    totals: input.totals,
    status: 'pending',
    createdAt: nowIso,
    updatedAt: nowIso,
    idempotencyKey,
  };

  const outbox: OutboxItem = {
    id: outboxId,
    saleId,
    request: {
      method: 'POST',
      url: input.endpointUrl,
      body: input.requestBody,
      headers: {
        'Content-Type': 'application/json',
        'Idempotency-Key': idempotencyKey,
        'X-Offline-Sale': 'true',
      },
    },
    status: 'pending',
    attempts: 0,
    createdAt: nowIso,
  };

  await offlineDb.transaction('rw', offlineDb.sales, offlineDb.outbox, async () => {
    await offlineDb.sales.add(sale);
    await offlineDb.outbox.add(outbox);
  });

  await logSyncEvent({
    eventType: 'sale_created_offline',
    saleId,
    detail: 'Venta almacenada localmente y encolada.',
  });

  return { sale, outbox };
};

const updateSaleStatus = async (
  saleId: string,
  status: LocalSaleStatus,
  extra: Partial<Pick<LocalSale, 'serverSaleId' | 'serverCode' | 'errorMessage'>> = {},
) => {
  await offlineDb.sales.update(saleId, {
    status,
    updatedAt: asIso(new Date()),
    ...extra,
  });
};

const processOutboxItem = async (item: OutboxItem) => {
  await updateSaleStatus(item.saleId, 'syncing', { errorMessage: undefined });

  const result = await sendApiRequest({
    method: item.request.method,
    url: item.request.url,
    body: item.request.body,
    headers: item.request.headers,
  });

  if (result.ok) {
    await offlineDb.transaction('rw', offlineDb.outbox, offlineDb.sales, async () => {
      await offlineDb.outbox.update(item.id, {
        status: 'done',
        lastError: undefined,
      });
      await updateSaleStatus(item.saleId, 'synced', {
        serverSaleId: result.data?.id ? String(result.data.id) : undefined,
        serverCode: buildServerSaleCode(result.data, item.saleId),
      });
    });
    await logSyncEvent({
      eventType: 'sale_sync_success',
      saleId: item.saleId,
      detail: 'Sincronización exitosa.',
    });
    return;
  }

  if (result.recoverable) {
    const attempts = item.attempts + 1;

    // Promote to permanent error if max attempts exceeded
    if (attempts >= MAX_SYNC_ATTEMPTS) {
      const errorMessage = result.errorMessage ?? 'Límite de reintentos alcanzado';
      await offlineDb.transaction('rw', offlineDb.outbox, offlineDb.sales, async () => {
        await offlineDb.outbox.update(item.id, {
          status: 'error',
          attempts,
          lastError: errorMessage,
          nextRetryAt: undefined,
        });
        await updateSaleStatus(item.saleId, 'error', { errorMessage });
      });
      await logSyncEvent({
        eventType: 'sale_sync_error',
        saleId: item.saleId,
        detail: `Máximo de reintentos alcanzado (#${attempts}): ${errorMessage}`,
      });
      return;
    }

    const nextRetryAt = asIso(new Date(Date.now() + nextRetryDelayMs(attempts)));
    await offlineDb.transaction('rw', offlineDb.outbox, offlineDb.sales, async () => {
      await offlineDb.outbox.update(item.id, {
        status: 'retrying',
        attempts,
        lastError: result.errorMessage ?? 'Error temporal',
        nextRetryAt,
      });
      await updateSaleStatus(item.saleId, 'pending', {
        errorMessage: result.errorMessage ?? 'Error temporal',
      });
    });
    await logSyncEvent({
      eventType: 'outbox_retry',
      saleId: item.saleId,
      detail: `Reintento #${attempts}: ${result.errorMessage ?? 'Error temporal'}`,
    });
    return;
  }

  const errorMessage = result.requiresLogin
    ? 'Requiere iniciar sesión'
    : (result.errorMessage ?? 'Error de validación');

  await offlineDb.transaction('rw', offlineDb.outbox, offlineDb.sales, async () => {
    await offlineDb.outbox.update(item.id, {
      status: 'error',
      attempts: item.attempts + 1,
      lastError: errorMessage,
      nextRetryAt: undefined,
    });
    await updateSaleStatus(item.saleId, 'error', { errorMessage });
  });

  await logSyncEvent({
    eventType: 'sale_sync_error',
    saleId: item.saleId,
    detail: errorMessage,
  });
};

export const processOutboxQueue = async () => {
  if (activeSyncPromise) {
    return activeSyncPromise;
  }

  activeSyncPromise = (async () => {
    const nowIso = asIso(new Date());
    const queue = await offlineDb.outbox
      .where('status')
      .anyOf(['pending', 'retrying'])
      .sortBy('createdAt');

    for (const item of queue) {
      if (item.nextRetryAt && item.nextRetryAt > nowIso) {
        continue;
      }
      await processOutboxItem(item);
    }
  })().finally(() => {
    activeSyncPromise = null;
  });

  return activeSyncPromise;
};

export const retrySaleSync = async (saleId: string) => {
  const outbox = await offlineDb.outbox.where('saleId').equals(saleId).first();
  if (!outbox) {
    return false;
  }

  // Do not retry items that completed successfully
  // (cast needed: Dexie's query inference narrows the union incorrectly here)
  if ((outbox.status as OutboxStatus) === 'done') {
    return false;
  }

  await offlineDb.outbox.update(outbox.id, {
    status: 'pending',
    nextRetryAt: undefined,
    lastError: undefined,
  });
  await updateSaleStatus(saleId, 'pending', { errorMessage: undefined });
  await processOutboxQueue();
  return true;
};

export const getPendingSalesCount = async () => {
  return offlineDb.sales.where('status').anyOf(['pending', 'syncing', 'error']).count();
};

export const getOutboxPendingCount = async () => {
  return offlineDb.outbox.where('status').anyOf(['pending', 'retrying']).count();
};

export const getRecentSales = async (limit = 20) => {
  const sales = await offlineDb.sales.orderBy('createdAt').reverse().limit(limit).toArray();
  return sales;
};
