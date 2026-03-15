import Button from 'devextreme-react/button';
import { DateTime } from 'luxon';
import React from 'react';
import { useNavigate } from 'react-router-dom';

import type {
  Notification,
  NotificationSeverity,
  NotificationType,
  UseNotificationsResult,
} from '../../../features/notifications';
import './NotificationPanel.scss';

interface NotificationPanelProps extends Pick<
  UseNotificationsResult,
  'notifications' | 'loading' | 'markRead' | 'markAllRead'
> {
  visible: boolean;
  onHiding: () => void;
}

const SEVERITY_ORDER: NotificationSeverity[] = ['critical', 'warning', 'info'];

const SEVERITY_ICON_CHAR: Record<NotificationSeverity, string> = {
  critical: '!',
  warning: '⚠',
  info: 'ℹ',
};

const TYPE_LABEL: Record<NotificationType, string> = {
  sunat_error: 'Error SUNAT',
  sunat_blocked: 'Bloqueado SUNAT',
  stock_low: 'Stock Bajo',
  cash_not_opened: 'Caja no abierta',
  quota_warning: 'Cuota',
  daily_briefing: 'Resumen Diario',
};

function relativeTime(isoString: string): string {
  const dt = DateTime.fromISO(isoString);
  if (!dt.isValid) return '';
  return dt.toRelative({ locale: 'es' }) ?? '';
}

function groupBySeverity(notifications: Notification[]): Notification[] {
  return [...notifications].sort((a, b) => {
    const ai = SEVERITY_ORDER.indexOf(a.severity);
    const bi = SEVERITY_ORDER.indexOf(b.severity);
    return ai - bi;
  });
}

const NotificationPanel = (props: NotificationPanelProps) => {
  const navigate = useNavigate();
  const { notifications, loading, markRead, markAllRead } = props;

  const unreadCount = notifications.filter((n) => n.readAt === null).length;

  const handleItemClick = (item: Notification) => {
    markRead(item.id);
    if (item.actionUrl) {
      navigate(item.actionUrl);
    }
    props.onHiding();
  };

  const sorted = groupBySeverity(notifications);

  return (
    <>
      <div className="notification-header">
        <div className="notification-header-title">
          Notificaciones
          {unreadCount > 0 && (
            <span
              className="notification-header-unread-count"
              aria-label={`${unreadCount} sin leer`}
            >
              ({unreadCount})
            </span>
          )}
        </div>
        <div className="notification-header-button">
          <Button icon="ph ph-x" stylingMode="text" onClick={props.onHiding} />
        </div>
      </div>

      <div className="notification-body" role="list" aria-label="Lista de notificaciones">
        {loading && notifications.length === 0 ? (
          <div className="notification-loading" aria-label="Cargando notificaciones">
            <span className="notification-spinner" role="status" />
          </div>
        ) : sorted.length === 0 ? (
          <div className="notification-empty">
            <span className="notification-empty-icon" aria-hidden="true">
              🔔
            </span>
            <span className="notification-empty-title">Sin notificaciones</span>
            <span className="notification-empty-subtitle">Todo está en orden</span>
          </div>
        ) : (
          sorted.map((item) => {
            const isRead = item.readAt !== null;
            const typeLabel = TYPE_LABEL[item.type];

            return (
              <div
                key={item.id}
                className={`notification-body-item notification-body-item--${item.severity}${isRead ? ' notification-body-item--read' : ''}`}
                role="listitem"
                tabIndex={0}
                aria-label={`${item.title}: ${item.body}`}
                onClick={() => handleItemClick(item)}
                onKeyDown={(e) => {
                  if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    handleItemClick(item);
                  }
                }}
              >
                <div
                  className={`notification-body-item-icon notification-body-item-icon--${item.severity}`}
                  aria-hidden="true"
                >
                  {SEVERITY_ICON_CHAR[item.severity]}
                </div>
                <div className="notification-body-item-title">
                  <div className="notification-body-item-title-row">
                    <div className="notification-body-item-title-text">{item.title}</div>
                    {!isRead && (
                      <span className="notification-body-item-unread-dot" aria-label="No leído" />
                    )}
                  </div>
                  {typeLabel && (
                    <span
                      className={`notification-type-badge notification-type-badge--${item.severity}`}
                    >
                      {typeLabel}
                    </span>
                  )}
                  <div className="notification-body-item-title-body">{item.body}</div>
                  <div className="notification-body-item-title-time">
                    {relativeTime(item.createdAt)}
                  </div>
                </div>
              </div>
            );
          })
        )}
      </div>

      <div className="notification-footer">
        <Button
          text="Marcar todo como leído"
          stylingMode="text"
          disabled={loading || notifications.every((n) => n.readAt !== null)}
          onClick={markAllRead}
        />
      </div>
    </>
  );
};

export default NotificationPanel;
