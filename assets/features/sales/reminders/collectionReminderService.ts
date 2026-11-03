import { getCoreApiBaseUrl } from '@nubitio/core';
import { httpClient } from '@shared/api/HttpClient';

export type ReminderChannel = 'whatsapp' | 'email';
export type ReminderTone = 'friendly' | 'direct' | 'final';
export type ReminderStatus = 'prepared' | 'sent' | 'failed';

export interface ReminderPreview {
  recipient: { name: string; phone: string | null; email: string | null; source: string };
  subject: string | null;
  body: string;
  documentUrl: string | null;
  daysOverdue: number;
  whatsappUrl: string | null;
  lastReminder: { channel: ReminderChannel; status: ReminderStatus; createdAt: string } | null;
}

export interface ReminderRecord {
  id: number;
  saleId: string;
  channel: ReminderChannel;
  tone: ReminderTone;
  status: ReminderStatus;
  body: string;
  failureReason: string | null;
  createdAt: string;
  whatsappUrl?: string | null;
}

export interface CreateReminderInput {
  saleId: string;
  channel: ReminderChannel;
  tone: ReminderTone;
  body?: string;
  force?: boolean;
}

const url = (path = '') => `${getCoreApiBaseUrl()}receivables/reminders${path}`;

// Errors are shown inside the dialog, so the global toast is turned off.
const INLINE = { errorPresentation: 'inline' } as const;

export const collectionReminderService = {
  async preview(
    saleId: string,
    channel: ReminderChannel,
    tone: ReminderTone,
    signal?: AbortSignal,
  ): Promise<ReminderPreview> {
    const response = await httpClient.post<ReminderPreview>(
      url('/preview'),
      { saleId, channel, tone },
      { ...INLINE, signal },
    );

    return response.data;
  },

  async create(input: CreateReminderInput): Promise<ReminderRecord> {
    const response = await httpClient.post<ReminderRecord>(url(), input, INLINE);

    return response.data;
  },
};
