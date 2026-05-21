import { SmartCrudPage, defineResource } from '@nubit/core';
import { sunatCatalogEvents } from '@app/domainEvents';
import { sunatCatalogEndpoints } from './api/catalogEndpoints';

const resource = defineResource({
  id: 'sunat-catalog',
  title: 'Catálogos SUNAT',
  apiUrl: sunatCatalogEndpoints.list(),
  fields: [],
  events: sunatCatalogEvents,
});
export const SunatCatalogPage = () => <SmartCrudPage resource={resource} />;
