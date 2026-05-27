import { SmartCrudPage, defineResource } from '@nubit/crud';
import { sunatCatalogEndpoints } from './api/catalogEndpoints';

const resource = defineResource(sunatCatalogEndpoints.list(), {
  id: 'sunat-catalog',
  title: 'Catálogos SUNAT',
});
export const SunatCatalogPage = () => <SmartCrudPage resource={resource} />;
