import { SmartCrudPage, defineResource } from '@core/crud';
import { unitOfMeasureEvents } from '@features/unitOfMeasure';
import { unitOfMeasureEndpoints } from '../api/catalogEndpoints';

const resource = defineResource({
  id: 'unitOfMeasure',
  title: 'Unidades de medida',
  apiUrl: unitOfMeasureEndpoints.list(),
  fields: [],
  events: unitOfMeasureEvents,
  allowAdd: true,
  allowEdit: true,
  allowDelete: true,
});
export const UnitOfMeasurePage = () => <SmartCrudPage resource={resource} />;
