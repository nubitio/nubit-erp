import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { Link, useNavigate } from 'react-router-dom';
import { controlPlaneClient } from '../api/controlPlaneClient';
import { RoleCheckboxes } from '../components/admin/RoleCheckboxes';
import { useControlPlaneRoles } from '../hooks/useControlPlaneRoles';

export const UserCreatePage = () => {
  const navigate = useNavigate();
  const { isSuperAdmin } = useControlPlaneRoles();
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [roles, setRoles] = useState<string[]>(['ROLE_ADMIN']);
  const [error, setError] = useState<string | null>(null);

  const create = useMutation({
    mutationFn: () =>
      controlPlaneClient.createAdminUser({
        username,
        password,
        confirmPassword,
        roles,
      }),
    onSuccess: (result) => {
      navigate(`/users/${result.id}`);
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

  return (
    <div className="cp-page">
      <header className="cp-page__header">
        <Link to="/users" className="cp-breadcrumb">
          ← Admin Users
        </Link>
        <h1>Create admin user</h1>
      </header>

      <section className="cp-card cp-card--narrow">
        {error && <div className="cp-alert cp-alert--danger">{error}</div>}
        <form
          className="cp-form"
          onSubmit={(e) => {
            e.preventDefault();
            create.mutate();
          }}
        >
          <label className="cp-field">
            <span>Username</span>
            <input
              value={username}
              onChange={(e) => setUsername(e.target.value)}
              pattern="[a-z0-9._-]{3,64}"
              required
              autoComplete="off"
            />
            <span className="cp-muted">
              3–64 chars: lowercase letters, digits, dots, hyphens, underscores.
            </span>
          </label>
          <label className="cp-field">
            <span>Password</span>
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
            <span>Confirm password</span>
            <input
              type="password"
              value={confirmPassword}
              onChange={(e) => setConfirmPassword(e.target.value)}
              minLength={12}
              required
              autoComplete="new-password"
            />
          </label>
          <div className="cp-field">
            <span>Roles</span>
            <RoleCheckboxes selected={roles} onChange={setRoles} />
          </div>
          <div className="cp-form__actions">
            <button type="submit" className="cp-btn cp-btn--primary" disabled={create.isPending}>
              Create user
            </button>
            <Link to="/users" className="cp-btn cp-btn--ghost">
              Cancel
            </Link>
          </div>
        </form>
      </section>
    </div>
  );
};