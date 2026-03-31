import { SmartCrudPage, defineResource } from '@core/crud';
import { configEvents } from '@features/config';
import { configEndpoints } from '../api/adminEndpoints';
import { FieldType } from '@core/field/FieldType';

const resource = defineResource({
  id: 'config',
  title: 'Configuraciones',
  apiUrl: configEndpoints.list(),
  fields: [],
  events: configEvents,
});
export const ConfigPage = () => (
  <SmartCrudPage
    resource={resource}
    fieldOverrides={[
      {
        key: 'type',
        type: FieldType.ENUM,
        data: [
          { value: 'string', text: 'Texto' },
          { value: 'int', text: 'Entero' },
          { value: 'float', text: 'Decimal' },
          { value: 'bool', text: 'Booleano' },
          { value: 'json', text: 'JSON' },
        ],
        valueField: 'value',
        textField: 'text',
        valueType: 'string',
        required: true,
      },
    ]}
  />
);
