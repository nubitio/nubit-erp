import { CrudPage, defineResource } from '@core/crud';
import { sunatCatalogEvents } from '@features/sunatCatalog';
import { SunatCatalogFields } from './SunatCatalogFields';
import { sunatCatalogEndpoints } from '../api/catalogEndpoints';

const resource = defineResource({
  id: 'sunat-catalog',
  title: 'Catálogos SUNAT',
  apiUrl: sunatCatalogEndpoints.list(),
  fields: SunatCatalogFields(),
  events: sunatCatalogEvents,
  allowAdd: true,
  allowEdit: true,
  allowDelete: true,
});
export const SunatCatalogPage = () => <CrudPage resource={resource} />;
