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
import { userInformation } from '@app/userInformation';
import { menuService } from '@features/navigation';
import { useNotifications } from '@features/notifications';
import { Drawer, SettingsPanel } from '@nubit/ui';
import { ThemeSwitcher } from './components/ThemeSwitcher';
import { UserMenuSection } from './components/UserMenuSection';
import { NotificationPanel } from './components/NotificationPanel';
import { BranchPicker } from './components/BranchPicker';
import { ChangePasswordModal } from './components/ChangePasswordModal';
import { SessionExpiredDialog } from './components/SessionExpiredDialog';

interface EfactAdminShellProps {
  title?: string;
  children: React.ReactNode;
}

export const EfactAdminShell = ({ title = 'efact', children }: EfactAdminShellProps) => {
  const [menuItems, setMenuItems] = useState<AdminMenuItem[]>([]);
  const [showBranchPicker, setShowBranchPicker] = useState(false);
  const [showChangePassword, setShowChangePassword] = useState(false);
  const [notifOpen, setNotifOpen] = useState(false);
  const { unreadCount, notifications, loading, markRead, markAllRead } = useNotifications();

  useEffect(() => {
    const roleId = userInformation().currentUserRoleId ?? 0;
    menuService
      .getMenuByRoleId(roleId)
      .then((response) => setMenuItems(response.data))
      .catch(() => setMenuItems([]));
  }, []);

  return (
    <>
      <AdminShell
        title={title}
        menuItems={menuItems}
        headerActions={[
          {
            id: 'notifications',
            icon: 'ph ph-bell',
            label: 'Notificaciones',
            badge: unreadCount,
            onClick: () => setNotifOpen((prev) => !prev),
          },
          {
            id: 'display-settings',
            icon: 'ph ph-sliders-horizontal',
            label: 'Apariencia',
            renderPanel: ({ close }) => <SettingsPanel onClose={close} />,
          },
        ]}
        renderUserMenu={({ close }) => (
          <UserMenuSection
            showAvatar
            onAction={close}
            onChangeBranch={() => setShowBranchPicker(true)}
            onChangePassword={() => setShowChangePassword(true)}
          />
        )}
        renderThemeSwitcher={() => <ThemeSwitcher />}
        footer={<span className="nb-admin-footer-copyright">© {new Date().getFullYear()} Nubit Labs</span>}
      >
        {children}
      </AdminShell>

      {/* Notification drawer */}
      <Drawer
        isOpen={notifOpen}
        onClose={() => setNotifOpen(false)}
        width={400}
        scrim="overlay"
        aria-label="Notificaciones"
      >
        <NotificationPanel
          visible={notifOpen}
          onHiding={() => setNotifOpen(false)}
          notifications={notifications}
          loading={loading}
          markRead={markRead}
          markAllRead={markAllRead}
        />
      </Drawer>

      <BranchPicker visible={showBranchPicker} setVisible={setShowBranchPicker} />
      <ChangePasswordModal
        visible={showChangePassword}
        onClose={() => setShowChangePassword(false)}
      />
      <SessionExpiredDialog />
    </>
  );
};
