/**
 * EfactAdminShell — eFact-specific adapter that wires the app-agnostic
 * AdminShell to eFact services (menuService, auth, notifications, assistant,
 * theme switcher, branch picker).
 *
 * This file is intentionally the single place that couples @nubitio/admin to
 * eFact domain concerns. Keep business logic out of @nubitio/admin and in here.
 */
import React, { lazy, Suspense, useEffect, useRef, useState } from 'react';
import { AdminShell, type AdminMenuItem } from '@nubitio/admin';
import { userInformation } from '@app/userInformation';
import { menuService } from '@features/navigation';
import { useNotifications } from '@features/notifications';
import { Drawer, SettingsPanel } from '@nubitio/ui';
import { ThemeSwitcher } from './components/ThemeSwitcher';
import { UserMenuSection } from './components/UserMenuSection';
import { NotificationPanel } from './components/NotificationPanel';
import { BranchPicker } from './components/BranchPicker';
import { ChangePasswordModal } from './components/ChangePasswordModal';
import { SessionExpiredDialog } from './components/SessionExpiredDialog';
import { useFeature, useTenantRuntimeConfig } from '@shared/hooks';

// Lazy-load the chat panel so it doesn't bloat the initial bundle
const Assistant = lazy(() => import('@features/assistant/Assistant'));

interface EfactAdminShellProps {
  title?: string;
  children: React.ReactNode;
}

export const EfactAdminShell = ({ title = 'efact', children }: EfactAdminShellProps) => {
  const [menuItems, setMenuItems] = useState<AdminMenuItem[]>([]);
  const [showBranchPicker, setShowBranchPicker] = useState(false);
  const [showChangePassword, setShowChangePassword] = useState(false);
  const [notifOpen, setNotifOpen] = useState(false);
  const [assistantOpen, setAssistantOpen] = useState(false);
  // Track whether the drawer has ever been opened so the Assistant component
  // stays mounted after first use — preserving conversation history across
  // open/close cycles. Without this, closing the drawer destroys React state.
  const assistantEverOpenedRef = useRef(false);
  if (assistantOpen) assistantEverOpenedRef.current = true;
  const { unreadCount, notifications, loading, markRead, markAllRead } = useNotifications();
  const { data: runtimeConfig, isError: runtimeConfigError } = useTenantRuntimeConfig();
  const assistantEnabled = useFeature('ai_assistant');

  useEffect(() => {
    const onOpen = () => {
      if (!assistantEnabled) {
        return;
      }
      setAssistantOpen(true);
    };
    window.addEventListener('efact:assistant-open', onOpen);
    return () => window.removeEventListener('efact:assistant-open', onOpen);
  }, [assistantEnabled]);

  useEffect(() => {
    if (!runtimeConfig && !runtimeConfigError) {
      return;
    }

    const roleId = userInformation().currentUserRoleId ?? 0;
    const menuRequest = runtimeConfig
      ? menuService.getMenuByRoleIdForRuntimeConfig(roleId, runtimeConfig)
      : menuService.getMenuByRoleId(roleId);

    menuRequest
      .then((response) => setMenuItems(response.data))
      .catch(() => setMenuItems([]));
  }, [runtimeConfig, runtimeConfigError]);

  return (
    <>
      <AdminShell
        title={title}
        menuItems={menuItems}
        headerActions={[
          ...(assistantEnabled
            ? [
                {
                  id: 'assistant',
                  icon: 'ph ph-sparkle',
                  label: 'Asistente de IA',
                  onClick: () => setAssistantOpen((prev) => !prev),
                },
              ]
            : []),
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
        footer={<span className="nb-admin-footer-copyright">© {new Date().getFullYear()} Efact</span>}
      >
        {children}
      </AdminShell>

      {assistantEnabled && (
        <Drawer
          isOpen={assistantOpen}
          onClose={() => setAssistantOpen(false)}
          title="Asistente de IA"
          width={520}
          scrim="subtle"
        >
          <Suspense fallback={null}>
            {assistantEverOpenedRef.current && <Assistant showToolbar={false} />}
          </Suspense>
        </Drawer>
      )}

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
