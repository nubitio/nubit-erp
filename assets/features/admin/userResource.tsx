import {
  defineFields,
  defineResource,
  passwordField,
  type DataRecord,
  type GridCellContext,
} from '@nubitio/crud';
import { Avatar, Badge } from '@nubitio/ui';
import { DateTime } from 'luxon';
import { entityLabel } from '@shared/lib/displayFormat';
import { userEndpoints } from './api/adminEndpoints';

interface UserRecord extends DataRecord {
  id: number;
  username: string;
  role: unknown;
  email?: string | null;
  lastLoginAt?: string | null;
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
    columns: ['username', 'role', 'lastLoginAt'],
  },
];

const usernameCell = (cell: GridCellContext) => {
  const email = (cell.data as UserRecord | undefined)?.email;
  return (
    <span className="user-cell">
      <Avatar owner={String(cell.value ?? '')} size={32} />
      <span className="user-cell__text">
        <strong>{String(cell.value ?? '')}</strong>
        {email ? <small>{email}</small> : null}
      </span>
    </span>
  );
};

/** "Hoy, 09:12" / "Ayer, 21:04" / "12/10/2026" / "—" (never signed in). */
export function formatLastLogin(value: unknown, now: DateTime = DateTime.now()): string {
  if (typeof value !== 'string' || value === '') return '—';
  const at = DateTime.fromISO(value).setZone(now.zone);
  if (!at.isValid) return '—';
  const days = now.startOf('day').diff(at.startOf('day'), 'days').days;
  if (days === 0) return `Hoy, ${at.toFormat('HH:mm')}`;
  if (days === 1) return `Ayer, ${at.toFormat('HH:mm')}`;
  return at.toFormat('dd/MM/yyyy');
}

const lastLoginCell = (cell: GridCellContext) => formatLastLogin(cell.value);

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
  filterRow: true,
  filterToolbarFields: ['username', 'role'],
  viewMode: 'dialog',
  defaultPreset: 'basic',
  columnPresets: USER_COLUMN_PRESETS,
  fieldContract: defineFields<UserRecord>({
    source: 'hydra',
    strategy: 'augment',
    directives: [
      { kind: 'override', key: 'username', patch: { label: 'Usuario', formatter: usernameCell } },
      { kind: 'override', key: 'role', patch: { label: 'Rol', formatter: roleCell } },
      { kind: 'override', key: 'email', patch: { label: 'Correo', visible: false } },
      {
        kind: 'override',
        key: 'lastLoginAt',
        patch: { label: 'Último ingreso', formatter: lastLoginCell, visibleOnForm: false },
      },
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
