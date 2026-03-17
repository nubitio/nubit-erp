import './UnitOfMeasure.scss';
import { CrudPage, defineResource } from '@core/crud';
import { unitOfMeasureEvents } from '@features/unitOfMeasure';
import { UnitOfMeasureFields } from './UnitOfMeasureFields';
import { unitOfMeasureEndpoints } from '../api/catalogEndpoints';

const resource = defineResource({
  id: 'unitOfMeasure',
  title: 'Unidades de medida',
  apiUrl: unitOfMeasureEndpoints.list(),
  fields: UnitOfMeasureFields(),
  events: unitOfMeasureEvents,
  allowAdd: true,
  allowEdit: true,
  allowDelete: true,
});
export const UnitOfMeasurePage = () => <CrudPage resource={resource} />;
