/**
 * EfactAdminShell — eFact-specific adapter that wires the app-agnostic
 * AdminShell to eFact services (menuService, auth, notifications, assistant,
 * theme switcher, branch picker).
 *
 * This file is intentionally the single place that couples @nubit/admin to
 * eFact domain concerns. Keep business logic out of @nubit/admin and in here.
 */
import React, { useEffect, useState } from 'react';
import { AdminShell, type AdminMenuItem } from '@nubit/admin';
import { userInformation } from '@nubit/core';
import { menuService } from '@features/navigation';
import { useNotifications } from '@features/notifications';
import { ThemeSwitcher } from './components/ThemeSwitcher';
import { UserMenuSection } from './components/UserMenuSection';
import { NotificationPanel } from './components/NotificationPanel';

interface EfactAdminShellProps {
  title?: string;
  children: React.ReactNode;
}

export const EfactAdminShell = ({ title = 'efact', children }: EfactAdminShellProps) => {
  const [menuItems, setMenuItems] = useState<AdminMenuItem[]>([]);
  const { unreadCount, notifications, loading, markRead, markAllRead } = useNotifications();

  useEffect(() => {
    const roleId = userInformation().currentUserRoleId ?? 0;
    menuService
      .getMenuByRoleId(roleId)
      .then((response) => setMenuItems(response.data as AdminMenuItem[]))
      .catch(() => setMenuItems([]));
  }, []);

  return (
    <AdminShell
      title={title}
      menuItems={menuItems}
      headerActions={[
        {
          id: 'notifications',
          icon: 'ph ph-bell',
          label: 'Notificaciones',
          badge: unreadCount,
          renderPanel: ({ close }) => (
            <div className="notification-popover">
              <NotificationPanel
                visible
                onHiding={close}
                notifications={notifications}
                loading={loading}
                markRead={markRead}
                markAllRead={markAllRead}
              />
            </div>
          ),
        },
      ]}
      renderUserMenu={({ close }) => <UserMenuSection showAvatar onAction={close} />}
      renderThemeSwitcher={() => <ThemeSwitcher />}
      footer={<span>Copyright © {new Date().getFullYear()} Nubit Labs</span>}
    >
      {children}
    </AdminShell>
  );
};
