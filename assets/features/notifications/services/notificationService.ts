import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();
import { httpClient } from '@shared/api/HttpClient';

export type NotificationType =
  | 'sunat_error'
  | 'sunat_blocked'
  | 'stock_low'
  | 'cash_not_opened'
  | 'quota_warning'
  | 'daily_briefing'
  | 'certificate_expiring'
  | 'sale_due_soon'
  | 'sale_overdue';

export type NotificationSeverity = 'critical' | 'warning' | 'info';

export interface Notification {
  id: number;
  type: NotificationType;
  severity: NotificationSeverity;
  title: string;
  body: string;
  actionUrl: string | null;
  readAt: string | null;
  createdAt: string;
}

class NotificationService {
  getNotifications(): Promise<Notification[]> {
    return httpClient
      .get<Notification[]>(API + 'user-notifications')
      .then((response) => response.data);
  }

  markRead(id: number): Promise<void> {
    return httpClient
      .patch<void>(API + `user-notifications/${id}/read`, {})
      .then(() => undefined);
  }

  markAllRead(): Promise<void> {
    return httpClient
      .post<void>(API + 'user-notifications/read-all')
      .then(() => undefined);
  }
}

export const notificationService = new NotificationService();
