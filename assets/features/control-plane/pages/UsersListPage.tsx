import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { controlPlaneClient } from '../api/controlPlaneClient';
import { CP_STRINGS } from '../i18n/es';
import { useControlPlaneRoles } from '../hooks/useControlPlaneRoles';

const roleBadgeClass = (role: string) => {
  if (role === 'ROLE_SUPER_ADMIN') return 'cp-badge--deleted';
  if (role === 'ROLE_ADMIN') return 'cp-badge--info';
  return '';
};

export const UsersListPage = () => {
  const queryClient = useQueryClient();
  const { isSuperAdmin, user: currentUser } = useControlPlaneRoles();

  const { data, isLoading, error } = useQuery({
    queryKey: ['control-plane', 'users'],
    queryFn: () => controlPlaneClient.getAdminUsers(),
  });

  const toggle = useMutation({
    mutationFn: (id: number) => controlPlaneClient.toggleAdminUser(id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['control-plane', 'users'] }),
  });

  if (!isSuperAdmin) {
    return (
      <div className="cp-page">
        <div className="cp-alert cp-alert--danger">Super-admin access required.</div>
      </div>
    );
  }

  if (isLoading) {
    return <div className="cp-page cp-page--loading">Cargando usuarios…</div>;
  }

  if (error || !data) {
    return (
      <div className="cp-page">
        <div className="cp-alert cp-alert--danger">
          No se pudo cargar usuarios: {(error as Error)?.message ?? 'Error desconocido'}
        </div>
      </div>
    );
  }

  const me = currentUser;

  return (
    <div className="cp-page">
      <header className="cp-page__header cp-page__header--split">
        <div>
          <h1>Admin Users</h1>
          <p>Manage control-plane administrator accounts.</p>
        </div>
        <Link to="/users/new" className="cp-btn cp-btn--primary">
          New admin user
        </Link>
      </header>

      <section className="cp-card">
        {data.users.length === 0 ? (
          <div className="cp-empty">No admin users found.</div>
        ) : (
          <table className="cp-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Roles</th>
                <th>Status</th>
                <th>Last login</th>
                <th>Created</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {data.users.map((user) => {
                const isSelf = me === user.username;
                return (
                  <tr key={user.id}>
                    <td className="cp-muted">{user.id}</td>
                    <td>
                      <strong>{user.username}</strong>
                      {isSelf && <span className="cp-badge cp-badge--info">you</span>}
                    </td>
                    <td>
                      {user.roles
                        .filter((role) => role !== 'ROLE_USER')
                        .map((role) => (
                          <span key={role} className={`cp-badge ${roleBadgeClass(role)}`}>
                            {role}
                          </span>
                        ))}
                    </td>
                    <td>
                      <span
                        className={`cp-badge ${user.is_active ? 'cp-badge--active' : ''}`}
                      >
                        {user.is_active ? CP_STRINGS.active : CP_STRINGS.inactive}
                      </span>
                    </td>
                    <td className="cp-muted">
                      {user.last_login_at ? user.last_login_at.slice(0, 16) : '—'}
                    </td>
                    <td className="cp-muted">
                      {user.created_at ? user.created_at.slice(0, 10) : '—'}
                    </td>
                    <td className="cp-table__actions">
                      <Link to={`/users/${user.id}`} className="cp-btn cp-btn--ghost">
                        Edit
                      </Link>
                      {!isSelf && (
                        <button
                          type="button"
                          className="cp-btn cp-btn--ghost"
                          disabled={toggle.isPending}
                          onClick={() => {
                            const action = user.is_active ? 'desactivar' : 'activar';
                            if (window.confirm(`${action} user "${user.username}"?`)) {
                              toggle.mutate(user.id);
                            }
                          }}
                        >
                          {user.is_active ? 'Desactivar' : 'Activar'}
                        </button>
                      )}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        )}
      </section>
    </div>
  );
};