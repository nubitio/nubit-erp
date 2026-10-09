import { useMemo } from 'react';
import { Outlet } from 'react-router-dom';
import { AdminShell, type AdminMenuItem } from '@nubitio/admin';
import { Avatar, ThemeSwitcher } from '@nubitio/ui';
import { useControlPlaneRoles } from '../hooks/useControlPlaneRoles';
import { CP_STRINGS } from '../i18n/es';

const ControlPlaneUserMenu = ({ user, close }: { user: string | null; close: () => void }) => (
  <div className="cp-user-menu">
    <div className="cp-user-menu__profile">
      <Avatar owner={user ?? 'admin'} size={32} />
      <span className="cp-user-menu__name">{user ?? '…'}</span>
    </div>
    <a href="/admin/logout" className="cp-user-menu__logout" onClick={close}>
      <i className="ph ph-sign-out" aria-hidden />
      Cerrar sesión
    </a>
  </div>
);

export const ControlPlaneShell = () => {
  const { user, isAdmin, isSuperAdmin } = useControlPlaneRoles();

  const menuItems = useMemo((): AdminMenuItem[] => {
    const items: AdminMenuItem[] = [
      { text: CP_STRINGS.nav.dashboard, path: '/', icon: 'gauge' },
      { text: CP_STRINGS.nav.tenants, path: '/tenants', icon: 'buildings' },
    ];

    if (isAdmin) {
      items.push({ text: CP_STRINGS.nav.plans, path: '/plans', icon: 'package' });
    }

    items.push({ text: CP_STRINGS.nav.auditLog, path: '/audit-log', icon: 'scroll' });

    if (isSuperAdmin) {
      items.push({ text: CP_STRINGS.nav.adminUsers, path: '/users', icon: 'shield-check' });
    }

    return items;
  }, [isAdmin, isSuperAdmin]);

  return (
    <AdminShell
      title="Control Plane"
      menuItems={menuItems}
      renderThemeSwitcher={() => <ThemeSwitcher />}
      renderUserMenu={({ close }) => <ControlPlaneUserMenu user={user} close={close} />}
      footer={<span className="nb-admin-footer-copyright">efact · {new Date().getFullYear()}</span>}
    >
      <Outlet />
    </AdminShell>
  );
};
