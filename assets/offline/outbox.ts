import { DateTime } from 'luxon';
import { getCoreTimezone } from '@nubitio/core';

import { offlineDb } from './db';
import { nextRetryDelayMs, MAX_OUTBOX_ATTEMPTS } from './backoff';
import { logSyncEvent } from './telemetry';
import { sendApiRequest } from './api';
import { getAppUpdateState, runCriticalOperation } from './appUpdate';
import { getAuthSessionState } from '@shared/lib/authSessionState';
import { belongsToAnotherUser, currentUserId } from './outboxOwnership';
import type {
  CreateOfflineSaleInput,
  LocalSale,
  LocalSaleStatus,
  OutboxItem,
  OutboxStatus,
} from './types';

/**
 * Generate a v4 UUID. Uses crypto.randomUUID() when available (secure contexts),
 * falls back to a Math.random-based implementation for HTTP/insecure contexts
 * such as local Docker dev and Playwright e2e environments.
 */
const generateUUID = (): string => {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID();
  }
  // RFC 4122 v4 fallback
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    const v = c === 'x' ? r : (r & 0x3) | 0x8;
    return v.toString(16);
  });
};

const OFFLINE_CORRELATIVE_PREFIX = 'OFF';
let activeSyncPromise: Promise<void> | null = null;

const asIso = (date: Date) => date.toISOString();

const buildTemporalCorrelative = async () => {
  // Use the configured business timezone (via CoreConfigProvider) so that
  // the daily correlative counter rolls over at local midnight for the business,
  // not at UTC midnight. This prevents duplicate correlatives for sales made
  // near the end of the business day.
  const businessTz = getCoreTimezone();
  const now = DateTime.now().setZone(businessTz);
  const year = now.year;
  const dayStart = now.startOf('day');
  const dayEnd = now.endOf('day');
  const count = await offlineDb.sales
    .where('createdAt')
    .between(dayStart.toUTC().toISO()!, dayEnd.toUTC().toISO()!, true, true)
    .count();
  return `${OFFLINE_CORRELATIVE_PREFIX}-${year}-${String(count + 1).padStart(6, '0')}`;
};

type ServerSaleResponse = {
  serie?: string;
  numero?: string;
  invoice?: string;
  id?: string;
};

const buildServerSaleCode = (created: ServerSaleResponse, fallback: string) => {
  const composed = [created?.serie, created?.numero].filter(Boolean).join('-');
  return composed || created?.invoice || created?.id || fallback;
};

export const createLocalSaleAndEnqueue = async (input: CreateOfflineSaleInput) => {
  const now = new Date();
  const nowIso = asIso(now);
  const saleId = generateUUID();
  const idempotencyKey = generateUUID();
  const correlativoTemporal = await buildTemporalCorrelative();
  const outboxId = generateUUID();
  const ownerUserId = currentUserId() ?? undefined;

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
    ownerUserId,
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
    ownerUserId,
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
  extra: Partial<
    Pick<
      LocalSale,
      | 'serverSaleId'
      | 'serverCode'
      | 'errorMessage'
      | 'errorCode'
      | 'errorAction'
      | 'numericErrorCode'
    >
  > = {},
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
      const created = (result.data ?? {}) as ServerSaleResponse;
      await updateSaleStatus(item.saleId, 'synced', {
        serverSaleId: created.id ? String(created.id) : undefined,
        serverCode: buildServerSaleCode(created, item.saleId),
        errorMessage: undefined,
        errorCode: undefined,
        errorAction: undefined,
        numericErrorCode: undefined,
      });
    });
    await logSyncEvent({
      eventType: 'sale_sync_success',
      saleId: item.saleId,
      detail: 'Sincronización exitosa.',
    });
    return;
  }

  if (result.requiresLogin) {
    await offlineDb.transaction('rw', offlineDb.outbox, offlineDb.sales, async () => {
      await offlineDb.outbox.update(item.id, {
        status: 'pending',
        nextRetryAt: undefined,
        lastError: 'Requiere iniciar sesión',
      });
      await updateSaleStatus(item.saleId, 'pending', { errorMessage: 'Requiere iniciar sesión' });
    });
    return;
  }

  if (result.recoverable) {
    const attempts = item.attempts + 1;

    // Promote to permanent error if max attempts exceeded
    if (attempts >= MAX_OUTBOX_ATTEMPTS) {
      const errorMessage = result.errorMessage ?? 'Límite de reintentos alcanzado';
      await offlineDb.transaction('rw', offlineDb.outbox, offlineDb.sales, async () => {
        await offlineDb.outbox.update(item.id, {
          status: 'error',
          attempts,
          lastError: errorMessage,
          lastErrorCode: result.errorCode,
          lastErrorAction: result.action,
          lastNumericErrorCode: result.numericCode,
          nextRetryAt: undefined,
        });
        await updateSaleStatus(item.saleId, 'error', {
          errorMessage,
          errorCode: result.errorCode,
          errorAction: result.action,
          numericErrorCode: result.numericCode,
        });
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
        lastErrorCode: result.errorCode,
        lastErrorAction: result.action,
        lastNumericErrorCode: result.numericCode,
        nextRetryAt,
      });
      await updateSaleStatus(item.saleId, 'pending', {
        errorMessage: result.errorMessage ?? 'Error temporal',
        errorCode: result.errorCode,
        errorAction: result.action,
        numericErrorCode: result.numericCode,
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
      lastErrorCode: result.errorCode,
      lastErrorAction: result.action,
      lastNumericErrorCode: result.numericCode,
      nextRetryAt: undefined,
    });
    await updateSaleStatus(item.saleId, 'error', {
      errorMessage,
      errorCode: result.errorCode,
      errorAction: result.action,
      numericErrorCode: result.numericCode,
    });
  });

  await logSyncEvent({
    eventType: 'sale_sync_error',
    saleId: item.saleId,
    detail: errorMessage,
  });
};

export const processOutboxQueue = async () => {
  if (getAuthSessionState().active === false) return;
  const update = getAppUpdateState();
  if (update.required || update.updating) return;
  if (activeSyncPromise) {
    return activeSyncPromise;
  }

  activeSyncPromise = runCriticalOperation(async () => {
    const nowIso = asIso(new Date());
    const queue = await offlineDb.outbox
      .where('status')
      .anyOf(['pending', 'retrying'])
      .sortBy('createdAt');

    const signedInUserId = currentUserId();
    for (const item of queue) {
      if (getAuthSessionState().active === false) break;
      // Someone else's sale waits for them: it must not be sent, and attributed, as ours.
      if (belongsToAnotherUser(item, signedInUserId)) continue;
      if (item.nextRetryAt && item.nextRetryAt > nowIso) {
        continue;
      }
      try {
        await processOutboxItem(item);
      } catch (err) {
        // Unexpected JS error (not HTTP) — log and continue so remaining
        // items in the queue are not blocked by a single broken item.
        await logSyncEvent({
          eventType: 'sale_sync_error',
          saleId: item.saleId,
          detail: err instanceof Error ? err.message : 'Error inesperado en sincronización',
        });
      }
    }
  })
    .catch((error: unknown) => {
      const current = getAppUpdateState();
      if (!current.required && !current.updating) throw error;
    })
    .finally(() => {
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

  // Reset attempts so the manual retry gets a full new window of MAX_OUTBOX_ATTEMPTS.
  // This is intentional: a merchant who waited hours before retrying deserves
  // another full backoff cycle, not the scraps of the previous one.
  await offlineDb.outbox.update(outbox.id, {
    status: 'pending',
    attempts: 0,
    nextRetryAt: undefined,
    lastError: undefined,
  });
  await updateSaleStatus(saleId, 'pending', {
    errorMessage: undefined,
    errorCode: undefined,
    errorAction: undefined,
    numericErrorCode: undefined,
  });
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
