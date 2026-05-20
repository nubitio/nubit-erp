// @nubit/api-platform — API Platform/Hydra/OpenAPI adapter.
// Import from this facade only; do not reach into implementation paths.
//
// Requires: <CoreConfigProvider locale="es" timezone="America/Lima"> from @nubit/core
// in the app provider tree so that useHydraMetadata picks up the correct locale.

export { ApiPlatformRemoteDataSource } from '@nubit/core';
export type {
  RemoteDataSourceOptions,
  RemoteFilterDescriptor,
  RemoteLoadOptions,
  RemoteSortDescriptor,
} from '@nubit/core';

export {
  parseHydraDoc,
  parseOpenApiDoc,
} from '@core/crud/hydra/openApiParser';

export {
  mapHydraSchemaToFields,
  resolveRangeTag,
} from '@core/crud/hydra/HydraToFieldMapper';

export {
  useHydraMetadata,
} from '@core/crud/hydra/useHydraMetadata';

export {
  useResourceSchema,
} from '@core/crud/hydra/useResourceSchema';

export {
  SchemaProvider,
  useSchemaContext,
} from '@core/crud/hydra/SchemaContext';

export type {
  UseSchemaContextResult,
} from '@core/crud/hydra/SchemaContext';

export type {
  HydraApiDoc,
  OpenApiDoc,
  ApiDoc,
} from '@core/crud/hydra/types';
