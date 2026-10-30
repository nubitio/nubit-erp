import {
  defineFields,
  defineResource,
  passwordField,
  type DataRecord,
  type GridCellContext,
} from '@nubitio/crud';
import { Avatar, Badge } from '@nubitio/ui';
import { entityLabel } from '@shared/lib/displayFormat';
import { userEndpoints } from './api/adminEndpoints';
import { RoleUserSummary } from './components/RoleUserSummary';

interface UserRecord extends DataRecord {
  id: number;
  username: string;
  role: unknown;
  plainPassword?: string;
}

const plainPasswordField = passwordField()
  .name('plainPassword')
  .label('Contraseña')
  .helpText('Requerida al crear un usuario.')
  .visible(false)
  .build();

const USER_COLUMN_PRESETS = [
  {
    key: 'basic',
    label: 'Básico',
    columns: ['username', 'role'],
  },
];

const usernameCell = (cell: GridCellContext) => (
  <span className="user-cell">
    <Avatar owner={String(cell.value ?? '')} size={32} />
    <strong>{String(cell.value ?? '')}</strong>
  </span>
);

const roleCell = (cell: GridCellContext) => {
  const name = entityLabel(cell.value);
  return name ? (
    <Badge pill variant="secondary">
      {name}
    </Badge>
  ) : null;
};

export const userResource = defineResource<UserRecord>(userEndpoints.list(), {
  id: 'user',
  title: 'Usuarios',
  gridAppearance: 'page',
  filterRow: false,
  filterToolbarFields: ['username', 'role'],
  viewMode: 'dialog',
  defaultPreset: 'basic',
  columnPresets: USER_COLUMN_PRESETS,
  aboveGrid: <RoleUserSummary />,
  fieldContract: defineFields<UserRecord>({
    source: 'hydra',
    strategy: 'augment',
    directives: [
      { kind: 'override', key: 'username', patch: { label: 'Usuario', formatter: usernameCell } },
      { kind: 'override', key: 'role', patch: { label: 'Rol', formatter: roleCell } },
      {
        kind: 'append',
        field: {
          ...plainPasswordField,
          operation: {
            only: 'create',
            required: { create: true },
          },
        },
      },
    ],
  }),
});
