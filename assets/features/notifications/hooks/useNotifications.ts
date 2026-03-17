import { useCallback, useEffect, useRef, useState } from 'react';
import { Subscription } from 'rxjs';

import { Constants } from '@core/common/Constants';
import { httpClient } from '@shared/api/HttpClient';
import type { Notification } from '../services/notificationService';
import { notificationService } from '../services/notificationService';

const POLL_INTERVAL_MS = 30_000;

export interface UseNotificationsResult {
  notifications: Notification[];
  unreadCount: number;
  markRead: (id: number) => void;
  markAllRead: () => void;
  loading: boolean;
}

export function useNotifications(): UseNotificationsResult {
  const [notifications, setNotifications] = useState<Notification[]>([]);
  const [loading, setLoading] = useState(true);
  const lastSubscription = useRef<Subscription | null>(null);
  const mountedRef = useRef(true);

  const fetchNotifications = useCallback(() => {
    lastSubscription.current?.unsubscribe();

    const subscription = httpClient
      .get<Notification[]>(Constants.API_BASE_URL + 'user-notifications')
      .subscribe({
        next: (response) => {
          if (!mountedRef.current) return;
          setNotifications(response.data ?? []);
          setLoading(false);
        },
        error: () => {
          if (!mountedRef.current) return;
          setLoading(false);
        },
      });

    lastSubscription.current = subscription;
  }, []);

  useEffect(() => {
    mountedRef.current = true;
    fetchNotifications();
    const timer = setInterval(fetchNotifications, POLL_INTERVAL_MS);

    return () => {
      mountedRef.current = false;
      clearInterval(timer);
      lastSubscription.current?.unsubscribe();
    };
  }, [fetchNotifications]);

  const markRead = useCallback(
    (id: number) => {
      // Optimistic update: immediately mark the notification as read in local state
      setNotifications((prev) =>
        prev.map((n) =>
          n.id === id && n.readAt === null ? { ...n, readAt: new Date().toISOString() } : n,
        ),
      );

      notificationService
        .markRead(id)
        .then(() => {
          if (mountedRef.current) fetchNotifications();
        })
        .catch(() => {
          // Revert the optimistic update by re-syncing with server
          if (mountedRef.current) fetchNotifications();
        });
    },
    [fetchNotifications],
  );

  const markAllRead = useCallback(() => {
    // Optimistic update: immediately mark all unread notifications as read in local state
    setNotifications((prev) =>
      prev.map((n) => (n.readAt === null ? { ...n, readAt: new Date().toISOString() } : n)),
    );

    notificationService
      .markAllRead()
      .then(() => {
        if (mountedRef.current) fetchNotifications();
      })
      .catch(() => {
        // Revert the optimistic update by re-syncing with server
        if (mountedRef.current) fetchNotifications();
      });
  }, [fetchNotifications]);

  const unreadCount = notifications.filter((n) => n.readAt === null).length;

  return { notifications, unreadCount, markRead, markAllRead, loading };
}
