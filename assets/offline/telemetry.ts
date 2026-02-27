import { offlineDb } from './db';
import type { SyncLog } from './types';

export const logSyncEvent = async (event: Omit<SyncLog, 'id' | 'createdAt'>) => {
  const payload: SyncLog = {
    ...event,
    createdAt: new Date().toISOString(),
  };
  await offlineDb.sync_logs.add(payload);
};
