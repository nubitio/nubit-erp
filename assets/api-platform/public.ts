// @nubit/api-platform — API Platform/Hydra/OpenAPI adapter.
// Import from this facade only; do not reach into implementation paths.
//
// Requires: <CoreConfigProvider locale="es" timezone="America/Lima"> from @nubit/core
// in the app provider tree so that useHydraMetadata picks up the correct locale.

export { ApiPlatformRemoteDataSource } from './ApiPlatformRemoteDataSource';
export type {
  RemoteDataSourceOptions,
  RemoteFilterDescriptor,
  RemoteLoadOptions,
  RemoteSortDescriptor,
} from './ApiPlatformRemoteDataSource';

export {
  parseHydraDoc,
  parseOpenApiDoc,
} from './openApiParser';

export {
  mapHydraSchemaToFields,
  resolveRangeTag,
} from './HydraToFieldMapper';

export {
  useHydraMetadata,
  API_DOC_QUERY_KEY,
} from './useHydraMetadata';

export {
  useResourceSchema,
} from './useResourceSchema';

export type { FieldOverride } from './useResourceSchema';

export {
  SchemaProvider,
  useSchemaContext,
} from './SchemaContext';

export type {
  UseSchemaContextResult,
} from './SchemaContext';

export type {
  HydraApiDoc,
  OpenApiDoc,
  ApiDoc,
} from './types';
