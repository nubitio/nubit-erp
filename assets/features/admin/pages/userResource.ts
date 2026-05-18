import { defineResource } from '@core/crud/defineResource';
import { defineFields } from '@core/crud/fieldContract';
import { passwordField } from '@core/field/FieldBuilders';
import { userEvents } from '@core/event/domainEvents';
import { userEndpoints } from '../api/adminEndpoints';
import type { DataRecord } from '@core/data/DataRecord';

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

export const userResource = defineResource<UserRecord>({
  id: 'user',
  title: 'Usuarios',
  apiUrl: userEndpoints.list(),
  fields: [],
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
  events: userEvents,
});
