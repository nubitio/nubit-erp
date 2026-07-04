import { Stack, Text, Toggle } from '@nubitio/ui';
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
  <Stack gap={3}>
    {ROLE_OPTIONS.map((role) => {
      const checked = selected.includes(role.value);
      return (
        <Toggle
          key={role.value}
          checked={checked}
          onChange={(next) => {
            if (next) {
              onChange([...selected, role.value]);
            } else {
              onChange(selected.filter((r) => r !== role.value));
            }
          }}
          label={
            <>
              <strong
                style={
                  'danger' in role && role.danger
                    ? { color: 'var(--error-color)' }
                    : undefined
                }
              >
                {role.label}
              </strong>
              <Text tone="muted"> — {role.description}</Text>
            </>
          }
        />
      );
    })}
  </Stack>
);