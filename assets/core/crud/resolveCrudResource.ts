import { createScopedEventBus } from '../event/createScopedEventBus';
import type { FormEventNames } from '../event/EventTypes';
import type { ResourceConfig } from './ResourceConfig';

export type ResolvedCrudResource<T extends Record<string, unknown>> = ResourceConfig<T> & {
  events: FormEventNames;
};

export function resolveCrudResource<T extends Record<string, unknown>>(
  resource: ResourceConfig<T>,
  overrides: Partial<ResourceConfig<T>> = {},
): ResolvedCrudResource<T> {
  const events = resource.events ?? createScopedEventBus(resource.id);

  return {
    ...resource,
    ...overrides,
    events,
  } as ResolvedCrudResource<T>;
}
