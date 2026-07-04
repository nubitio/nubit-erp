import { NavLink, Outlet } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { controlPlaneClient } from '../api/controlPlaneClient';
import { useControlPlaneRoles } from '../hooks/useControlPlaneRoles';
import { CP_STRINGS } from '../i18n/es';

const navItems = [
  { to: '/', label: CP_STRINGS.nav.dashboard, icon: 'bi-speedometer2', end: true },
  { to: '/tenants', label: CP_STRINGS.nav.tenants, icon: 'bi-building' },
];

export const ControlPlaneShell = () => {
  const session = useQuery({
    queryKey: ['control-plane', 'session'],
    queryFn: () => controlPlaneClient.getSession(),
  });

  const { isAdmin, isSuperAdmin } = useControlPlaneRoles();

  return (
    <div className="cp-shell">
      <aside className="cp-sidebar">
        <a href="/admin" className="cp-brand">
          <span className="cp-brand__icon">
            <i className="bi bi-receipt" />
          </span>
          <span>
            <span className="cp-brand__title">efact</span>
            <span className="cp-brand__subtitle">Control Plane</span>
          </span>
        </a>

        <nav className="cp-nav">
          <div className="cp-nav__label">Management</div>
          {navItems.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              end={item.end}
              className={({ isActive }) => `cp-nav__link${isActive ? ' cp-nav__link--active' : ''}`}
            >
              <i className={`bi ${item.icon}`} />
              <span>{item.label}</span>
            </NavLink>
          ))}
          {isAdmin && (
            <NavLink
              to="/plans"
              className={({ isActive }) => `cp-nav__link${isActive ? ' cp-nav__link--active' : ''}`}
            >
              <i className="bi bi-box-seam" />
              <span>{CP_STRINGS.nav.plans}</span>
            </NavLink>
          )}
          <div className="cp-nav__label">System</div>
          <NavLink
            to="/audit-log"
            className={({ isActive }) => `cp-nav__link${isActive ? ' cp-nav__link--active' : ''}`}
          >
            <i className="bi bi-journal-text" />
            <span>{CP_STRINGS.nav.auditLog}</span>
          </NavLink>
          {isSuperAdmin && (
            <NavLink
              to="/users"
              className={({ isActive }) => `cp-nav__link${isActive ? ' cp-nav__link--active' : ''}`}
            >
              <i className="bi bi-shield-lock" />
              <span>{CP_STRINGS.nav.adminUsers}</span>
            </NavLink>
          )}
        </nav>

        <div className="cp-sidebar__footer">
          <div className="cp-user">
            <i className="bi bi-person-circle" />
            <span>{session.data?.user ?? '…'}</span>
          </div>
          <a href="/admin/logout" className="cp-logout">
            <i className="bi bi-box-arrow-left" />
            Cerrar sesión
          </a>
        </div>
      </aside>

      <main className="cp-main">
        <Outlet />
      </main>
    </div>
  );
};