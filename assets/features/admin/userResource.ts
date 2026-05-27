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

export const userResource = defineResource<UserRecord>(userEndpoints.list(), {
  id: 'user',
  title: 'Usuarios',
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
