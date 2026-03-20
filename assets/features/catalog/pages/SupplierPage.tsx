import { SmartCrudPage, defineResource } from '@core/crud';
import { supplierEvents } from '@features/supplier';
import { supplierEndpoints } from '../api/catalogEndpoints';

const resource = defineResource({
  id: 'supplier',
  title: 'Proveedores',
  apiUrl: supplierEndpoints.list(),
  fields: [],
  events: supplierEvents,
  allowAdd: true,
  allowEdit: true,
  allowDelete: true,
});
export const SupplierPage = () => <SmartCrudPage resource={resource} />;
