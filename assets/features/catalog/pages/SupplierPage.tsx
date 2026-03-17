import './SupplierPage.scss';
import { CrudPage, defineResource } from '@core/crud';
import { supplierEvents } from '@features/supplier';
import { SupplierFields } from './SupplierFields';
import { supplierEndpoints } from '../api/catalogEndpoints';

const resource = defineResource({
  id: 'supplier',
  title: 'Proveedores',
  apiUrl: supplierEndpoints.list(),
  fields: (formRef) => SupplierFields(formRef),
  events: supplierEvents,
  allowAdd: true,
  allowEdit: true,
  allowDelete: true,
});
export const SupplierPage = () => <CrudPage resource={resource} />;
