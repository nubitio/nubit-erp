import React, { useState } from 'react';
import { DateTime } from 'luxon';
import { useNavigate } from 'react-router-dom';
import { EmptyState, IconButton, Skeleton } from '@nubit/ui';

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

// ── Category mapping ────────────────────────────────────────────────────────

type Category = 'all' | 'sunat' | 'stock' | 'system';

const CATEGORY_LABEL: Record<Category, string> = {
  all: 'Todos',
  sunat: 'SUNAT',
  stock: 'Stock',
  system: 'Sistema',
};

const TYPE_CATEGORY: Record<NotificationType, Category> = {
  sunat_error:     'sunat',
  sunat_blocked:   'sunat',
  stock_low:       'stock',
  cash_not_opened: 'system',
  quota_warning:   'system',
  daily_briefing:  'system',
};

// ── Icon config per type ────────────────────────────────────────────────────

interface IconConfig {
  icon: string;
  bg: string;   // CSS color for the icon square background
  color: string; // CSS color for the icon itself
}

const TYPE_ICON: Record<NotificationType, IconConfig> = {
  sunat_error:     { icon: 'ph ph-warning',       bg: 'var(--status-danger-bg)',   color: 'var(--error-color)' },
  sunat_blocked:   { icon: 'ph ph-seal-warning',   bg: 'var(--status-danger-bg)',   color: 'var(--error-color)' },
  stock_low:       { icon: 'ph ph-cube',           bg: 'var(--status-warning-bg)',  color: 'var(--warning-color)' },
  cash_not_opened: { icon: 'ph ph-cash-register',  bg: 'var(--status-info-bg)',     color: 'var(--info-color)' },
  quota_warning:   { icon: 'ph ph-chart-bar',      bg: 'var(--status-warning-bg)',  color: 'var(--warning-color)' },
  daily_briefing:  { icon: 'ph ph-seal-check',     bg: 'var(--status-success-bg)',  color: 'var(--success-color)' },
};

const SEVERITY_DOT_COLOR: Record<NotificationSeverity, string> = {
  critical: 'var(--error-color)',
  warning:  'var(--warning-color)',
  info:     'var(--accent-color)',
};

// ── Action link label per type ───────────────────────────────────────────────

const TYPE_ACTION_LABEL: Partial<Record<NotificationType, string>> = {
  sunat_error:     'Ver comprobante',
  sunat_blocked:   'Ver comprobante',
  stock_low:       'Ver inventario',
  cash_not_opened: 'Abrir caja',
  quota_warning:   'Ver configuración',
};

// ── Time formatting ──────────────────────────────────────────────────────────

function formatTime(isoString: string): string {
  const dt = DateTime.fromISO(isoString);
  if (!dt.isValid) return '';
  const now = DateTime.now();
  if (dt.hasSame(now, 'day')) return dt.toFormat('HH:mm');
  if (dt.hasSame(now.minus({ days: 1 }), 'day')) return 'Ayer';
  return dt.toFormat('dd/MM');
}

// ── Component ────────────────────────────────────────────────────────────────

const NotificationPanel = (props: NotificationPanelProps) => {
  const navigate = useNavigate();
  const { notifications, loading, markRead, markAllRead } = props;
  const [activeCategory, setActiveCategory] = useState<Category>('all');

  const unreadCount = notifications.filter((n) => n.readAt === null).length;
  const allRead = notifications.every((n) => n.readAt !== null);

  // Category counts (unread only for the badge, total for filter)
  const categoryCounts = (['sunat', 'stock', 'system'] as Category[]).reduce<Record<Category, number>>(
    (acc, cat) => {
      acc[cat] = notifications.filter((n) => TYPE_CATEGORY[n.type] === cat).length;
      return acc;
    },
    { all: notifications.length, sunat: 0, stock: 0, system: 0 },
  );

  const filtered = activeCategory === 'all'
    ? notifications
    : notifications.filter((n) => TYPE_CATEGORY[n.type] === activeCategory);

  const handleItemClick = (item: Notification) => {
    markRead(item.id);
    if (item.actionUrl) {
      navigate(item.actionUrl);
      props.onHiding();
    }
  };

  const handleActionLink = (e: React.MouseEvent, item: Notification) => {
    e.stopPropagation();
    markRead(item.id);
    if (item.actionUrl) {
      navigate(item.actionUrl);
      props.onHiding();
    }
  };

  const handleDismiss = (e: React.MouseEvent, id: number) => {
    e.stopPropagation();
    markRead(id);
  };

  return (
    <div className="notif-panel">
      {/* ── Header ─────────────────────────────────────────────────────────── */}
      <div className="notif-panel__header">
        <div className="notif-panel__header-left">
          <span className="notif-panel__title">Notificaciones</span>
          {unreadCount > 0 && (
            <span className="notif-panel__badge" aria-label={`${unreadCount} sin leer`}>
              {unreadCount}
            </span>
          )}
        </div>
        <div className="notif-panel__header-right">
          <button
            type="button"
            className="notif-panel__mark-all"
            disabled={loading || allRead}
            onClick={markAllRead}
          >
            Marcar todo leído
          </button>
          <IconButton
            icon="ph ph-x"
            label="Cerrar notificaciones"
            onClick={props.onHiding}
          />
        </div>
      </div>

      {/* ── Category filters ────────────────────────────────────────────────── */}
      <div className="notif-panel__filters" role="tablist" aria-label="Filtrar por categoría">
        {(['all', 'sunat', 'stock', 'system'] as Category[]).map((cat) => {
          const count = categoryCounts[cat];
          if (cat !== 'all' && count === 0) return null;
          return (
            <button
              key={cat}
              type="button"
              role="tab"
              aria-selected={activeCategory === cat}
              className={`notif-panel__filter-tab${activeCategory === cat ? ' is-active' : ''}`}
              onClick={() => setActiveCategory(cat)}
            >
              {CATEGORY_LABEL[cat]}
              {count > 0 && (
                <span className={`notif-panel__filter-count${activeCategory === cat ? ' is-active' : ''}`}>
                  {count}
                </span>
              )}
            </button>
          );
        })}
      </div>

      {/* ── Body ───────────────────────────────────────────────────────────── */}
      <div className="notif-panel__body" role="list" aria-label="Lista de notificaciones">
        {loading && notifications.length === 0 ? (
          <div className="notif-panel__loading" aria-live="polite">
            <Skeleton variant="circle" width={36} height={36} />
            <Skeleton variant="text" width="60%" height={14} />
          </div>
        ) : filtered.length === 0 ? (
          <EmptyState
            icon="bell"
            title="Sin notificaciones"
            description="Todo está en orden"
            size="sm"
          />
        ) : (
          filtered.map((item) => {
            const isRead = item.readAt !== null;
            const iconCfg = TYPE_ICON[item.type];
            const actionLabel = TYPE_ACTION_LABEL[item.type];
            const dotColor = SEVERITY_DOT_COLOR[item.severity];

            return (
              <div
                key={item.id}
                className={`notif-item${isRead ? ' notif-item--read' : ''}`}
                role="listitem"
                tabIndex={0}
                onClick={() => handleItemClick(item)}
                onKeyDown={(e) => {
                  if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); handleItemClick(item); }
                }}
              >
                {/* Icon square */}
                <div
                  className="notif-item__icon"
                  style={{ background: iconCfg.bg, color: iconCfg.color }}
                  aria-hidden="true"
                >
                  <i className={iconCfg.icon} />
                </div>

                {/* Content */}
                <div className="notif-item__content">
                  <div className="notif-item__title-row">
                    {!isRead && (
                      <span
                        className="notif-item__dot"
                        style={{ background: dotColor }}
                        aria-label="Sin leer"
                      />
                    )}
                    <span className="notif-item__title">{item.title}</span>
                    <span className="notif-item__time">{formatTime(item.createdAt)}</span>
                  </div>
                  <p className="notif-item__body">{item.body}</p>
                  {item.actionUrl && actionLabel && (
                    <button
                      type="button"
                      className="notif-item__action"
                      onClick={(e) => handleActionLink(e, item)}
                    >
                      {actionLabel} →
                    </button>
                  )}
                </div>

                {/* Dismiss */}
                <button
                  type="button"
                  className="notif-item__dismiss"
                  aria-label="Descartar"
                  onClick={(e) => handleDismiss(e, item.id)}
                >
                  ×
                </button>
              </div>
            );
          })
        )}
      </div>
    </div>
  );
};

export { NotificationPanel };
export default NotificationPanel;
