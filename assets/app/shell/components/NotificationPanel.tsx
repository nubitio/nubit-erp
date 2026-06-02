import React from 'react';
import { DateTime } from 'luxon';
import { useNavigate } from 'react-router-dom';
import { Badge, Button, EmptyState, IconButton, Skeleton } from '@nubit/ui';

import type {
  Notification,
  NotificationSeverity,
  NotificationType,
  UseNotificationsResult,
} from '@features/notifications';

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
          <IconButton
            className="notification-close-button"
            icon="ph ph-x"
            label="Cerrar notificaciones"
            onClick={props.onHiding}
          />
        </div>
      </div>

      <div className="notification-body" role="list" aria-label="Lista de notificaciones">
        {loading && notifications.length === 0 ? (
          <div className="notification-loading" aria-label="Cargando notificaciones">
            <Skeleton variant="circle" width={28} height={28} />
          </div>
        ) : sorted.length === 0 ? (
          <EmptyState
            icon="bell"
            title="Sin notificaciones"
            description="Todo está en orden"
            size="sm"
          />
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
                    <Badge
                      variant={item.severity === 'critical' ? 'danger' : item.severity === 'warning' ? 'warning' : 'info'}
                      size="sm"
                      pill
                      className="notification-type-badge"
                    >
                      {typeLabel}
                    </Badge>
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
          variant="ghost"
          size="sm"
          disabled={loading || notifications.every((n) => n.readAt !== null)}
          onClick={markAllRead}
        >
          Marcar todo como leído
        </Button>
      </div>
    </>
  );
};

export { NotificationPanel };
export default NotificationPanel;
