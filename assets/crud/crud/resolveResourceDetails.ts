import type { DataRecord } from '@nubit/core';
import type { ResourceConfig, ResourceFormDetail, ResourceGridDetail } from './ResourceConfig';

export interface ResolvedResourceDetails {
  gridDetail?: ResourceGridDetail;
  formDetail?: ResourceFormDetail;
}

export function resolveResourceDetails<T extends DataRecord>(
  resource: ResourceConfig<T>,
): ResolvedResourceDetails {
  const gridDetail = resource.gridDetail ?? (
    resource.detailUrl && (resource.gridDetailFields ?? resource.detailFields)
      ? {
          url: resource.detailUrl,
          fields: resource.gridDetailFields ?? resource.detailFields!,
        }
      : undefined
  );

  const formDetail = resource.formDetail ?? (
    resource.detailFields
      ? {
          url: resource.detailUrl,
          fields: resource.detailFields,
          propertyName: resource.detailPropertyName,
          allowAdding: resource.detailAllowAdding,
          allowDeleting: resource.detailAllowDeleting,
          allowUpdating: resource.detailAllowUpdating,
          required: resource.detailRequired,
        }
      : undefined
  );

  return { gridDetail, formDetail };
}
