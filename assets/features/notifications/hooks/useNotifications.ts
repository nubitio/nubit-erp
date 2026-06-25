import { useCallback, useEffect, useRef, useState } from 'react';

import { getCoreApiBaseUrl, MercureManager, useMercureHub } from '@nubitio/core';

const API = getCoreApiBaseUrl();
import { httpClient } from '@shared/api/HttpClient';
import type { Notification } from '../services/notificationService';
import { notificationService } from '../services/notificationService';
import { useSessionInfo } from '@shared/sessionInfo';

/**
 * Fallback poll interval used only when the Mercure hub is not yet available
 * (e.g. first load before the hub URL has been discovered from a response header).
 * Once Mercure is connected the interval is cleared and real-time push takes over.
 */
const FALLBACK_POLL_INTERVAL_MS = 120_000;

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

  const hubUrl = useMercureHub();
  const { currentUserId } = useSessionInfo();

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

  // ── Initial fetch + fallback poll (only when Mercure is not available) ───────
  useEffect(() => {
    mountedRef.current = true;
    fetchNotifications();

    const refreshIfVisible = () => {
      if (document.visibilityState === 'visible') {
        fetchNotifications();
      }
    };

    // Fallback polling: disabled when Mercure hub is already known
    let timer: ReturnType<typeof setInterval> | undefined;
    if (!hubUrl) {
      timer = setInterval(refreshIfVisible, FALLBACK_POLL_INTERVAL_MS);
    }

    // Always re-fetch on tab focus / visibility change — cheap catch-up for
    // any Mercure gaps while the tab was hidden.
    document.addEventListener('visibilitychange', refreshIfVisible);
    window.addEventListener('focus', refreshIfVisible);

    return () => {
      mountedRef.current = false;
      if (timer !== undefined) clearInterval(timer);
      document.removeEventListener('visibilitychange', refreshIfVisible);
      window.removeEventListener('focus', refreshIfVisible);
      lastAbortRef.current?.abort();
    };
  }, [fetchNotifications, hubUrl]);

  // ── Mercure real-time subscription ──────────────────────────────────────────
  // Topic: {origin}/api/users/{userId}/notifications
  // Published by UserNotificationService after each DB insert.
  useEffect(() => {
    if (!hubUrl || !currentUserId) return;

    const topic = `${window.location.origin}/api/users/${String(currentUserId)}/notifications`;

    const handler = (data: unknown) => {
      if (!mountedRef.current) return;

      const incoming = data as Partial<Notification> & { id?: number };

      // If the payload includes enough fields, prepend it directly to avoid
      // an extra HTTP round-trip. Otherwise fall back to a full re-fetch.
      if (incoming?.id && incoming.type && incoming.title) {
        setNotifications((prev) => {
          if (prev.some((n) => n.id === incoming.id)) return prev; // dedupe
          return [incoming as Notification, ...prev];
        });
      } else {
        // Minimal push (just a ping) — re-fetch to get the full list.
        fetchNotifications();
      }
    };

    MercureManager.subscribe(topic, handler);

    return () => {
      MercureManager.unsubscribe(topic, handler);
    };
  }, [hubUrl, currentUserId, fetchNotifications]);

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
