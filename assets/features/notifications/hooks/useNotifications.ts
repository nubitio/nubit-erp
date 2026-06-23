import { useCallback, useEffect, useRef, useState } from 'react';

import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();
import { httpClient } from '@shared/api/HttpClient';
import type { Notification } from '../services/notificationService';
import { notificationService } from '../services/notificationService';

const POLL_INTERVAL_MS = 120_000;

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
  const lastAbortRef = useRef<AbortController | null>(null);
  const mountedRef = useRef(true);

  const fetchNotifications = useCallback(() => {
    lastAbortRef.current?.abort();
    const controller = new AbortController();
    lastAbortRef.current = controller;

    httpClient
      .get<Notification[]>(API + 'user-notifications', { signal: controller.signal })
      .then((response) => {
        if (!mountedRef.current) return;
        setNotifications(response.data ?? []);
        setLoading(false);
      })
      .catch(() => {
        if (!mountedRef.current) return;
        setLoading(false);
      });
  }, []);

  useEffect(() => {
    mountedRef.current = true;
    fetchNotifications();

    const refreshIfVisible = () => {
      if (document.visibilityState === 'visible') {
        fetchNotifications();
      }
    };

    const timer = setInterval(refreshIfVisible, POLL_INTERVAL_MS);
    document.addEventListener('visibilitychange', refreshIfVisible);
    window.addEventListener('focus', refreshIfVisible);

    return () => {
      mountedRef.current = false;
      clearInterval(timer);
      document.removeEventListener('visibilitychange', refreshIfVisible);
      window.removeEventListener('focus', refreshIfVisible);
      lastAbortRef.current?.abort();
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
      .catch(() => {
        // Revert the optimistic update by re-syncing with server
        if (mountedRef.current) fetchNotifications();
      });
  }, [fetchNotifications]);

  const unreadCount = notifications.filter((n) => n.readAt === null).length;

  return { notifications, unreadCount, markRead, markAllRead, loading };
}
