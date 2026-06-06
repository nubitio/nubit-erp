import { defineFields, defineResource, passwordField, type DataRecord } from '@nubit/crud';
import { userEndpoints } from './api/adminEndpoints';

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

const USER_FORM_LAYOUT = {
  type: 'tabs' as const,
  tabs: [
    {
      label: 'Acceso',
      icon: 'ph-user',
      fields: ['username', 'role', 'plainPassword'],
    },
  ],
};

export const userResource = defineResource<UserRecord>(userEndpoints.list(), {
  id: 'user',
  title: 'Usuarios',
  viewMode: { mode: 'drawer', drawerWidth: 560 },
  defaultPreset: 'basic',
  columnPresets: USER_COLUMN_PRESETS,
  formLayout: USER_FORM_LAYOUT,
  fieldContract: defineFields<UserRecord>({
    source: 'hydra',
    strategy: 'augment',
    directives: [
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
