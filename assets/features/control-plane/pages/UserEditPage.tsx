import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Link, useParams, useSearchParams } from 'react-router-dom';
import { controlPlaneClient } from '../api/controlPlaneClient';
import { RoleCheckboxes } from '../components/admin/RoleCheckboxes';
import { useControlPlaneRoles } from '../hooks/useControlPlaneRoles';

export const UserEditPage = () => {
  const { id } = useParams<{ id: string }>();
  const userId = Number(id);
  const [searchParams, setSearchParams] = useSearchParams();
  const queryClient = useQueryClient();
  const { isSuperAdmin, user: currentUser } = useControlPlaneRoles();

  const detail = useQuery({
    queryKey: ['control-plane', 'users', userId],
    queryFn: () => controlPlaneClient.getAdminUser(userId),
    enabled: Number.isFinite(userId) && userId > 0,
  });

  const [roles, setRoles] = useState<string[] | null>(null);
  const [isActive, setIsActive] = useState<boolean | null>(null);
  const [password, setPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [error, setError] = useState<string | null>(null);

  const activeTab = searchParams.get('tab') === 'password' ? 'password' : 'general';
  const user = detail.data?.user;
  const selectedRoles = roles ?? user?.roles ?? [];
  const activeStatus = isActive ?? user?.is_active ?? true;
  const isSelf = currentUser === user?.username;

  const save = useMutation({
    mutationFn: () =>
      controlPlaneClient.updateAdminUser(userId, {
        roles: selectedRoles,
        isActive: activeStatus,
      }),
    onSuccess: () => {
      setError(null);
      queryClient.invalidateQueries({ queryKey: ['control-plane', 'users', userId] });
      queryClient.invalidateQueries({ queryKey: ['control-plane', 'users'] });
    },
    onError: (err: Error) => setError(err.message),
  });

  const resetPassword = useMutation({
    mutationFn: () =>
      controlPlaneClient.resetAdminUserPassword(userId, { password, confirmPassword }),
    onSuccess: () => {
      setPassword('');
      setConfirmPassword('');
      setError(null);
    },
    onError: (err: Error) => setError(err.message),
  });

  if (!isSuperAdmin) {
    return (
      <div className="cp-page">
        <div className="cp-alert cp-alert--danger">Super-admin access required.</div>
      </div>
    );
  }

  if (!Number.isFinite(userId) || userId <= 0) {
    return <div className="cp-page cp-alert cp-alert--danger">Invalid user ID.</div>;
  }

  if (detail.isLoading) {
    return <div className="cp-page cp-page--loading">Cargando usuario…</div>;
  }

  if (detail.error || !user) {
    return (
      <div className="cp-page">
        <div className="cp-alert cp-alert--danger">
          {(detail.error as Error)?.message ?? 'User not found'}
        </div>
        <Link to="/users" className="cp-btn cp-btn--ghost">
          ← Back to users
        </Link>
      </div>
    );
  }

  return (
    <div className="cp-page">
      <header className="cp-page__header cp-page__header--split">
        <div>
          <Link to="/users" className="cp-breadcrumb">
            ← Admin Users
          </Link>
          <h1>{user.username}</h1>
          <p>Edit roles, status, and password.</p>
        </div>
        <span className={`cp-badge ${user.is_active ? 'cp-badge--active' : ''}`}>
          {user.is_active ? 'Active' : 'Inactive'}
        </span>
      </header>

      <nav className="cp-tabs" aria-label="User sections">
        <button
          type="button"
          className={`cp-tabs__item${activeTab === 'general' ? ' cp-tabs__item--active' : ''}`}
          onClick={() => setSearchParams({})}
        >
          General
        </button>
        <button
          type="button"
          className={`cp-tabs__item${activeTab === 'password' ? ' cp-tabs__item--active' : ''}`}
          onClick={() => setSearchParams({ tab: 'password' })}
        >
          Password
        </button>
      </nav>

      <div className="cp-tab-panel">
        {error && <div className="cp-alert cp-alert--danger">{error}</div>}

        {activeTab === 'general' && (
          <section className="cp-card cp-card--narrow">
            <form
              className="cp-form"
              onSubmit={(e) => {
                e.preventDefault();
                save.mutate();
              }}
            >
              <label className="cp-field">
                <span>Username</span>
                <input value={user.username} readOnly disabled />
                <span className="cp-muted">Username cannot be changed after creation.</span>
              </label>
              <div className="cp-field">
                <span>Roles</span>
                <RoleCheckboxes selected={selectedRoles} onChange={setRoles} />
              </div>
              <label className="cp-check cp-check--block">
                <input
                  type="checkbox"
                  checked={activeStatus}
                  disabled={isSelf}
                  onChange={(e) => setIsActive(e.target.checked)}
                />
                <span>Active</span>
              </label>
              {isSelf && (
                <div className="cp-alert cp-alert--warning">
                  You cannot deactivate your own account.
                </div>
              )}
              <div className="cp-form__actions">
                <button type="submit" className="cp-btn cp-btn--primary" disabled={save.isPending}>
                  Save changes
                </button>
                <Link to="/users" className="cp-btn cp-btn--ghost">
                  Cancel
                </Link>
              </div>
            </form>
          </section>
        )}

        {activeTab === 'password' && (
          <section className="cp-card cp-card--narrow">
            <p className="cp-muted">
              Setting a new password for <strong>{user.username}</strong> immediately invalidates
              the current one.
            </p>
            <form
              className="cp-form"
              onSubmit={(e) => {
                e.preventDefault();
                if (
                  !window.confirm(
                    `Reset password for "${user.username}"? This invalidates the current password.`,
                  )
                ) {
                  return;
                }
                resetPassword.mutate();
              }}
            >
              <label className="cp-field">
                <span>New password</span>
                <input
                  type="password"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  minLength={12}
                  required
                  autoComplete="new-password"
                />
              </label>
              <label className="cp-field">
                <span>Confirm new password</span>
                <input
                  type="password"
                  value={confirmPassword}
                  onChange={(e) => setConfirmPassword(e.target.value)}
                  minLength={12}
                  required
                  autoComplete="new-password"
                />
              </label>
              <button
                type="submit"
                className="cp-btn cp-btn--ghost"
                disabled={resetPassword.isPending}
              >
                Change password
              </button>
            </form>
          </section>
        )}
      </div>
    </div>
  );
};