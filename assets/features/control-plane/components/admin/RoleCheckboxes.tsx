const ROLE_OPTIONS = [
  {
    value: 'ROLE_CP_VIEWER',
    label: 'ROLE_CP_VIEWER',
    description: 'Read-only access to audit logs and dashboards',
  },
  {
    value: 'ROLE_ADMIN',
    label: 'ROLE_ADMIN',
    description: 'Manage tenants, plans, and provisioning',
  },
  {
    value: 'ROLE_SUPER_ADMIN',
    label: 'ROLE_SUPER_ADMIN',
    description: 'Full access including admin user management',
    danger: true,
  },
] as const;

interface RoleCheckboxesProps {
  selected: string[];
  onChange: (roles: string[]) => void;
}

export const RoleCheckboxes = ({ selected, onChange }: RoleCheckboxesProps) => (
  <div className="cp-role-list">
    {ROLE_OPTIONS.map((role) => {
      const checked = selected.includes(role.value);
      return (
        <label key={role.value} className="cp-check cp-check--block">
          <input
            type="checkbox"
            checked={checked}
            onChange={(e) => {
              if (e.target.checked) {
                onChange([...selected, role.value]);
              } else {
                onChange(selected.filter((r) => r !== role.value));
              }
            }}
          />
          <span>
            <strong className={role.danger ? 'cp-text-danger' : ''}>{role.label}</strong>
            <span className="cp-muted"> — {role.description}</span>
          </span>
        </label>
      );
    })}
  </div>
);