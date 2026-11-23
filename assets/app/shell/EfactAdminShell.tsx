/* eslint-disable react-hooks/refs -- legacy shell bridge reads stable refs during render; remove with shell state migration */
/**
 * EfactAdminShell — eFact-specific adapter that wires the app-agnostic
 * AdminShell to eFact services (menuService, auth, notifications, assistant,
 * theme switcher, branch picker).
 *
 * This file is intentionally the single place that couples @nubitio/admin to
 * eFact domain concerns. Keep business logic out of @nubitio/admin and in here.
 */
import React, { lazy, Suspense, useEffect, useRef, useState } from 'react';
import { useLocation } from 'react-router-dom';
import { AdminShell, AdminBottomNavigation } from '@nubitio/admin';
import { userInformation } from '@app/userInformation';
import { useWorkspaceMenu } from '@features/navigation';
import { useNotifications } from '@features/notifications';
import { Drawer } from '@nubitio/ui';
import { ThemeSwitcher } from './components/ThemeSwitcher';
import { AccountPreferencesSync, SettingsDialog } from '@app/profile';
import { UserAvatar } from '@shared/components';
import { UserMenuSection } from './components/UserMenuSection';
import { NotificationPanel } from './components/NotificationPanel';
import { BranchPicker } from './components/BranchPicker';
import { useFeature, useTenantRuntimeConfig } from '@shared/hooks';
import { useAuth } from '@app/contexts/auth';
import { WorkspaceSearch } from './components/WorkspaceSearch';
import { WorkspaceBrand } from './components/WorkspaceBrand';
import {
  mobileWorkspaceDestinations,
  workspaceSectionTitle,
} from './components/workspaceNavigation';
import './workspace.scss';

// Lazy-load the chat panel so it doesn't bloat the initial bundle
const Assistant = lazy(() => import('@features/assistant/Assistant'));

interface EfactAdminShellProps {
  title?: string;
  children: React.ReactNode;
}

export const EfactAdminShell = ({ title = 'Nubit ERP', children }: EfactAdminShellProps) => {
  // Committed branch changes also refresh the auth context.
  const { user } = useAuth();
  const { items: menuItems } = useWorkspaceMenu();
  const [showBranchPicker, setShowBranchPicker] = useState(false);
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
  const branchName =
    userInformation().currentBranchName ||
    runtimeConfig?.defaults.branch?.name ||
    'Sin sucursal seleccionada';
  const canChangeBranch = runtimeConfig?.ui.shell.showBranchPicker === true || runtimeConfigError;
  const mobileDestinations = mobileWorkspaceDestinations(menuItems);
  const { pathname } = useLocation();
  const sectionTitle = workspaceSectionTitle(pathname, menuItems, title);
  const branchContent = (
    <>
      <i className="ph ph-building" aria-hidden="true" />
      <span>
        <strong>Sucursal actual</strong>
        <small>{branchName}</small>
      </span>
    </>
  );

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

  return (
    <>
      <AccountPreferencesSync />
      <AdminShell
        variant="workspace"
        brand={<WorkspaceBrand />}
        headerContent={<WorkspaceSearch menu={menuItems} />}
        sidebarContext={
          canChangeBranch ? (
            <button
              className="erp-workspace-context"
              type="button"
              aria-label={`Cambiar sucursal: ${branchName}`}
              onClick={() => setShowBranchPicker(true)}
            >
              {branchContent}
              <i className="ph ph-caret-down" aria-hidden="true" />
            </button>
          ) : (
            <div className="erp-workspace-context">{branchContent}</div>
          )
        }
        bottomNavigation={
          mobileDestinations.length > 0 ? (
            <AdminBottomNavigation label="Accesos principales" items={mobileDestinations} />
          ) : undefined
        }
        title={sectionTitle}
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
        ]}
        userAvatar={
          <UserAvatar
            name={user?.name || user?.username || ''}
            avatarUrl={user?.avatarUrl}
            size={28}
          />
        }
        renderUserMenu={({ close }) => (
          <UserMenuSection
            showAvatar
            onAction={close}
            onChangeBranch={() => setShowBranchPicker(true)}
          />
        )}
        renderThemeSwitcher={() => <ThemeSwitcher />}
        footer={
          <span className="nb-admin-footer-copyright">
            © {new Date().getFullYear()} Nubit ERP
            {window.app_version && window.app_version !== 'dev' && (
              <span className="nb-admin-footer-version"> · {window.app_version}</span>
            )}
          </span>
        }
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
      <SettingsDialog />
    </>
  );
};
