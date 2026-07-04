import { Card } from '@nubitio/ui';
import { CP_STRINGS } from '../../i18n/es';

const ROLE_OPTIONS = [
  {
    value: 'ROLE_CP_VIEWER',
    label: CP_STRINGS.roles.viewer.label,
    description: CP_STRINGS.roles.viewer.description,
  },
  {
    value: 'ROLE_ADMIN',
    label: CP_STRINGS.roles.admin.label,
    description: CP_STRINGS.roles.admin.description,
  },
  {
    value: 'ROLE_SUPER_ADMIN',
    label: CP_STRINGS.roles.superAdmin.label,
    description: CP_STRINGS.roles.superAdmin.description,
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
        <Card variant="panel" key={role.value}>
          <label className="cp-check cp-check--block">
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
              <strong className={'danger' in role && role.danger ? 'cp-text-danger' : ''}>
                {role.label}
              </strong>
              <span className="cp-muted"> — {role.description}</span>
            </span>
          </label>
        </Card>
      );
    })}
  </div>
);